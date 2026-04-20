<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Verification;

use App\Dto\Editorial\ClaimOriginGraph;
use App\Dto\NotebookLM\FactCheckResult;
use App\Entity\Editorial\SourceSignal;
use App\Entity\Editorial\VerifiedSource;
use App\Entity\Source;
use App\Entity\Topic;
use App\Enum\Editorial\VerdictType;
use App\Enum\EditorialAlignment;
use App\Repository\AppSettingRepository;
use App\Service\Ai\LlmRetryExecutor;
use App\Service\Ai\TierResolver;
use App\Service\Editorial\Llm\LlmInvocationLogger;
use App\Service\Editorial\Verification\VerificationGate;
use App\Service\NotebookLM\NotebookLmFactCheckServiceInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Sprint 55 T55.10 — real NotebookLM invocation with downgrade-only override.
 *
 * Complements {@see VerificationGateNotebookLmTest} which exercises the
 * S54 log-only hook. This test focuses on the new behaviour introduced by
 * T55.10:
 *   - actual factCheckClaim invocation when Topic is passed
 *   - downgrade to ESCALATE_HUMAN on contradiction
 *   - no upgrade (downgrade-only direction)
 *   - fail-open on service throw / null return
 *   - fallback to log-only when topic=null or service=null
 */
class VerificationGateNotebookLmRealInvocationTest extends TestCase
{
    private LlmRetryExecutor&MockObject $executor;
    private TierResolver&MockObject $tierResolver;
    private AppSettingRepository&MockObject $settings;
    private LlmInvocationLogger&MockObject $invocationLogger;
    private NotebookLmFactCheckServiceInterface&MockObject $factCheck;
    private VerificationGate $gate;

    protected function setUp(): void
    {
        $this->executor = $this->createMock(LlmRetryExecutor::class);
        $this->tierResolver = $this->createMock(TierResolver::class);
        $this->settings = $this->createMock(AppSettingRepository::class);
        $this->invocationLogger = $this->createMock(LlmInvocationLogger::class);
        $this->factCheck = $this->createMock(NotebookLmFactCheckServiceInterface::class);

        // Feature flag ON — every test here exercises the post-gate path.
        $this->settings->method('getBool')
            ->willReturnMap([
                ['notebooklm.factcheck.enabled', false, true],
                // Verification-gate's own tier enabled flag — default true.
                ['agent.verification_gate.enabled', true, true],
            ]);
        $this->settings->method('get')->willReturnArgument(1);
        $this->tierResolver->method('isEnabled')->willReturn(false);

        $this->gate = new VerificationGate(
            $this->executor,
            $this->tierResolver,
            $this->settings,
            $this->invocationLogger,
            new NullLogger(),
            $this->factCheck,
        );
    }

    public function testContradictoryAnswerDowngradesYellowToEscalate(): void
    {
        $this->factCheck->expects($this->once())
            ->method('factCheckClaim')
            ->willReturn($this->makeResult('Această informație este falsă conform surselor oficiale.'));

        $topic = $this->makeTopic();
        // Yellow verdict path — 2 chains same alignment, no Rule-0 keyword.
        // Title carries HIGH_STAKES_TOPIC_WORD "alegeri" so the hook engages
        // the NotebookLM check. Without the NotebookLM contradiction we'd
        // stay at FLASH_WITH_ASSERTION_YELLOW; WITH it we downgrade to
        // ESCALATE_HUMAN per T55.10 contract.
        $signals = [
            $this->makeSignal(1, 'Sondaj contestă rezultatele alegerilor', 'wire_neutral'),
            $this->makeSignal(2, 'Raport similar despre alegeri', 'wire_neutral'),
        ];
        $graph = $this->makeGraph(2, ['wire_neutral'], [1 => 2]);

        $verdict = $this->gate->rule($graph, $signals, $topic);

        $this->assertSame(VerdictType::ESCALATE_HUMAN, $verdict->type);
        $this->assertStringContainsString('NotebookLM', $verdict->reasoning);
    }

    public function testConsistentAnswerDoesNotChangeVerdict(): void
    {
        $this->factCheck->method('factCheckClaim')
            ->willReturn($this->makeResult('Afirmația este susținută de mai multe surse independente.'));

        $topic = $this->makeTopic();
        // Yellow verdict path: 2 chains + same alignment + tier-1 present, no
        // Rule-0 escalation keywords. Title uses HIGH_STAKES_TOPIC_WORD
        // "alegeri" which keeps the NotebookLM hook interested without
        // forcing ESCALATE_HUMAN.
        $signals = [
            $this->makeSignal(1, 'Sondaj: alegeri parlamentare rămân contestate', 'wire_neutral'),
            $this->makeSignal(2, 'Rapport secundar despre alegeri', 'wire_neutral'),
        ];
        $graph = $this->makeGraph(2, ['wire_neutral'], [1 => 2]);

        $verdict = $this->gate->rule($graph, $signals, $topic);

        // Rule 2 → FLASH_WITH_ASSERTION_YELLOW. Hook fires (yellow + high-stakes
        // topic word), factCheck returns consistent answer → verdict unchanged.
        $this->assertSame(VerdictType::FLASH_WITH_ASSERTION_YELLOW, $verdict->type);
    }

    public function testNeverUpgradesVerdict(): void
    {
        // The "upgrade" direction (ESCALATE_HUMAN → FULL_FLASH on NotebookLM
        // "confirmed") is disabled by design. Force the gate into ESCALATE_HUMAN
        // via a Rule-0 escalation keyword, then have NotebookLM reply "susținut"
        // and assert the verdict stays ESCALATE_HUMAN.
        $this->factCheck->method('factCheckClaim')
            ->willReturn($this->makeResult('Este susținut de surse oficiale.'));

        $topic = $this->makeTopic();
        $signals = [$this->makeSignal(1, 'Nuclear strike on Kyiv reported', 'wire_neutral')];
        $graph = $this->makeGraph(1, ['wire_neutral'], [1 => 1]);

        $verdict = $this->gate->rule($graph, $signals, $topic);

        $this->assertSame(VerdictType::ESCALATE_HUMAN, $verdict->type);
    }

    public function testServiceThrowsFailOpenOriginalVerdict(): void
    {
        $this->factCheck->method('factCheckClaim')->willThrowException(new \RuntimeException('CLI timeout'));

        $topic = $this->makeTopic();
        $signals = [$this->makeSignal(1, 'Nuclear strike on Kyiv', 'wire_neutral')];
        $graph = $this->makeGraph(1, ['wire_neutral'], [1 => 1]);

        $verdict = $this->gate->rule($graph, $signals, $topic);

        // Rule-0 produced ESCALATE_HUMAN, gate didn't crash, verdict preserved.
        $this->assertSame(VerdictType::ESCALATE_HUMAN, $verdict->type);
    }

    public function testServiceReturnsNullFailOpenOriginalVerdict(): void
    {
        $this->factCheck->method('factCheckClaim')->willReturn(null);

        $topic = $this->makeTopic();
        $signals = [$this->makeSignal(1, 'Nuclear strike on Kyiv', 'wire_neutral')];
        $graph = $this->makeGraph(1, ['wire_neutral'], [1 => 1]);

        $verdict = $this->gate->rule($graph, $signals, $topic);

        $this->assertSame(VerdictType::ESCALATE_HUMAN, $verdict->type);
    }

    public function testNullTopicFallsBackToLogOnly(): void
    {
        // When topic is null, gate must NOT call the fact-check service
        // (no notebook to target) — falls back to S54 log-only hook.
        $this->factCheck->expects($this->never())->method('factCheckClaim');

        $signals = [$this->makeSignal(1, 'Nuclear strike on Kyiv', 'wire_neutral')];
        $graph = $this->makeGraph(1, ['wire_neutral'], [1 => 1]);

        $verdict = $this->gate->rule($graph, $signals, null);

        $this->assertSame(VerdictType::ESCALATE_HUMAN, $verdict->type);
    }

    public function testNonHighStakesVerdictSkipsFactCheckEntirely(): void
    {
        // FULL_FLASH never routes through NotebookLM — alignment diversity
        // already provides safety, so the gate should not incur the cost.
        $this->factCheck->expects($this->never())->method('factCheckClaim');

        $topic = $this->makeTopic();
        $signals = [
            $this->makeSignal(1, 'Generic news update', 'wire_neutral'),
            $this->makeSignal(2, 'Same event from western outlet', 'western_mainstream'),
        ];
        // Rule 3: 2 chains + tier-1 + alignment-diverse → FULL_FLASH.
        $graph = $this->makeGraph(2, ['wire_neutral', 'western_mainstream'], [1 => 2]);

        $verdict = $this->gate->rule($graph, $signals, $topic);

        $this->assertSame(VerdictType::FULL_FLASH, $verdict->type);
    }

    public function testFeatureFlagOffSkipsFactCheckEvenWithTopic(): void
    {
        // Override getBool to return false for the factcheck flag.
        $settings = $this->createMock(AppSettingRepository::class);
        $settings->method('getBool')->willReturn(false);
        $settings->method('get')->willReturnArgument(1);

        $factCheck = $this->createMock(NotebookLmFactCheckServiceInterface::class);
        $factCheck->expects($this->never())->method('factCheckClaim');

        $gate = new VerificationGate(
            $this->executor,
            $this->tierResolver,
            $settings,
            $this->invocationLogger,
            new NullLogger(),
            $factCheck,
        );

        $topic = $this->makeTopic();
        $signals = [$this->makeSignal(1, 'Nuclear strike on Kyiv', 'wire_neutral')];
        $graph = $this->makeGraph(1, ['wire_neutral'], [1 => 1]);

        $verdict = $gate->rule($graph, $signals, $topic);

        $this->assertSame(VerdictType::ESCALATE_HUMAN, $verdict->type);
    }

    // ========== Helpers ==========

    private function makeResult(string $answer): FactCheckResult
    {
        return new FactCheckResult(
            answer: $answer,
            question: 'test question',
            topicId: 1,
            notebookId: 'nb-1',
            cached: false,
            checkedAt: new \DateTimeImmutable(),
        );
    }

    private function makeTopic(): Topic
    {
        $topic = new Topic();
        $topic->setTitle('Test Topic');
        $topic->setSlug('test-topic');
        $topic->setNotebookLmId('nb-1');

        $ref = new \ReflectionProperty(Topic::class, 'id');
        $ref->setValue($topic, 42);

        return $topic;
    }

    private function makeSignal(int $id, string $title, string $alignment): SourceSignal
    {
        $source = new Source();
        $verified = new VerifiedSource(
            slug: 'src-' . $id,
            tier: 1,
            editorialAlignment: EditorialAlignment::from($alignment),
            trustScoreBaseline: '0.7',
            source: $source,
        );

        $signal = new SourceSignal(
            verifiedSource: $verified,
            sourceUrl: sprintf('https://example.com/%d', $id),
            title: $title,
            rawContentHash: str_repeat((string) $id, 64),
        );
        $signal->setRawSummary('stub summary for signal ' . $id);

        $ref = new \ReflectionProperty(SourceSignal::class, 'id');
        $ref->setValue($signal, $id);

        return $signal;
    }

    /**
     * @param int             $chains              independent_chains value for the D3 matrix
     * @param list<string>    $alignmentClusters   distinct alignment buckets in the cluster
     * @param array<int, int> $tierDistribution    tier → node_count
     */
    private function makeGraph(int $chains, array $alignmentClusters, array $tierDistribution): ClaimOriginGraph
    {
        return ClaimOriginGraph::fromArray([
            'topic_hash' => 'topic-hash-42',
            'claim_hash' => 'claim-hash-1',
            'nodes' => [],
            'edges' => [],
            'alignment_clusters' => $alignmentClusters,
            'independent_chains' => $chains,
            'tier_distribution' => $tierDistribution,
        ]);
    }
}
