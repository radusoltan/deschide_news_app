<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Verification;

use App\Dto\Editorial\ClaimOriginGraph;
use App\Entity\Editorial\SourceSignal;
use App\Entity\Editorial\VerifiedSource;
use App\Entity\Source;
use App\Enum\EditorialAlignment;
use App\Repository\AppSettingRepository;
use App\Service\Ai\LlmRetryExecutor;
use App\Service\Ai\TierResolver;
use App\Service\Editorial\Verification\VerificationGate;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * Sprint 54 T54.10 — NotebookLM log-only hook.
 *
 * The hook fires only when BOTH the feature flag
 * `notebooklm.factcheck.enabled` is true AND the computed verdict is
 * high-stakes:
 *   - ESCALATE_HUMAN → always
 *   - FLASH_WITH_ASSERTION_YELLOW → only if a HIGH_STAKES_TOPIC_WORDS
 *     pattern matches (broader than escalation keywords on purpose).
 * FULL_FLASH, FLASH_WITH_ATTRIBUTION, REJECT never trigger the hook.
 */
class VerificationGateNotebookLmTest extends TestCase
{
    private LlmRetryExecutor&MockObject $executor;
    private TierResolver&MockObject $tierResolver;
    private AppSettingRepository&MockObject $settings;
    private CapturingLogger $logger;
    private VerificationGate $gate;

    protected function setUp(): void
    {
        $this->executor = $this->createMock(LlmRetryExecutor::class);
        $this->tierResolver = $this->createMock(TierResolver::class);
        $this->settings = $this->createMock(AppSettingRepository::class);
        $this->logger = new CapturingLogger();

        $this->gate = new VerificationGate(
            $this->executor,
            $this->tierResolver,
            $this->settings,
            $this->logger,
        );
    }

    public function testFeatureFlagOffSkipsHookEvenOnEscalateVerdict(): void
    {
        $this->settings->method('getBool')
            ->with('notebooklm.factcheck.enabled', false)
            ->willReturn(false);
        $this->settings->method('get')->willReturnArgument(1);

        $this->executor->expects($this->never())->method('executeWithRetry');

        $signal = $this->makeSignal(1, 'Nuclear strike reported');
        $graph = $this->makeGraph(1, ['wire_neutral'], ['1' => 1]);

        $this->gate->rule($graph, [$signal]);

        $this->assertEmpty(
            $this->logger->findByChannel('verification_notebooklm_would_invoke'),
            'NotebookLM hook must not fire when feature flag is off.',
        );
    }

    public function testFeatureFlagOnEscalateVerdictEmitsHook(): void
    {
        $this->settings->method('getBool')
            ->with('notebooklm.factcheck.enabled', false)
            ->willReturn(true);
        $this->settings->method('get')->willReturnArgument(1);

        $signal = $this->makeSignal(1, 'Nuclear strike on Kyiv reported');
        $graph = $this->makeGraph(1, ['wire_neutral'], ['1' => 1]);

        $this->gate->rule($graph, [$signal]);

        $hookLogs = $this->logger->findByChannel('verification_notebooklm_would_invoke');
        $this->assertCount(1, $hookLogs);
        $this->assertSame('escalate_verdict', $hookLogs[0]['context']['high_stakes_reason']);
        $this->assertSame('escalate_human', $hookLogs[0]['context']['verdict_before_notebooklm']);
        $this->assertArrayHasKey('claim', $hookLogs[0]['context']);
        $this->assertArrayHasKey('topic_hash', $hookLogs[0]['context']);
    }

    public function testFeatureFlagOnFullFlashNeverEmitsHook(): void
    {
        $this->settings->method('getBool')
            ->with('notebooklm.factcheck.enabled', false)
            ->willReturn(true);
        $this->settings->method('get')->willReturnArgument(1);
        $this->tierResolver->method('isEnabled')->willReturn(false);

        $graph = $this->makeGraph(
            chains: 3,
            alignments: ['wire_neutral', 'md_independent_pro_eu', 'kremlin_aligned'],
            tiers: ['1' => 2, '2' => 1],
        );
        $signal = $this->makeSignal(1, 'Summit UE confirmat');

        $this->gate->rule($graph, [$signal]);

        $this->assertEmpty(
            $this->logger->findByChannel('verification_notebooklm_would_invoke'),
            'FULL_FLASH must never trigger NotebookLM double-check.',
        );
    }

    public function testFeatureFlagOnYellowWithoutTopicWordSkipsHook(): void
    {
        $this->settings->method('getBool')
            ->with('notebooklm.factcheck.enabled', false)
            ->willReturn(true);
        $this->settings->method('get')->willReturnArgument(1);
        $this->tierResolver->method('isEnabled')->willReturn(false);

        $graph = $this->makeGraph(
            chains: 2,
            alignments: ['kremlin_aligned'],
            tiers: ['1' => 1, '2' => 1],
        );
        $signal = $this->makeSignal(1, 'Poziție oficială despre economie');

        $this->gate->rule($graph, [$signal]);

        $this->assertEmpty(
            $this->logger->findByChannel('verification_notebooklm_would_invoke'),
        );
    }

    public function testFeatureFlagOnYellowWithTopicWordEmitsHook(): void
    {
        $this->settings->method('getBool')
            ->with('notebooklm.factcheck.enabled', false)
            ->willReturn(true);
        $this->settings->method('get')->willReturnArgument(1);
        $this->tierResolver->method('isEnabled')->willReturn(false);

        // Yellow verdict shape + high-stakes TOPIC word. "transnistria"
        // alone doesn't trip Rule 0 escalation (needs "+ armată/atac") but
        // IS in HIGH_STAKES_TOPIC_WORDS so NotebookLM should double-check
        // when same-alignment coverage surfaces it.
        $graph = $this->makeGraph(
            chains: 2,
            alignments: ['kremlin_aligned'],
            tiers: ['1' => 1, '2' => 1],
        );
        $signal = $this->makeSignal(
            1,
            'Schimbare pe linia de contact',
            summary: 'Rapoarte despre economia din transnistria',
        );

        $this->gate->rule($graph, [$signal]);

        $hookLogs = $this->logger->findByChannel('verification_notebooklm_would_invoke');
        $this->assertCount(1, $hookLogs);
        $this->assertStringStartsWith('yellow_with_topic:', $hookLogs[0]['context']['high_stakes_reason']);
        $this->assertSame('flash_with_assertion_yellow', $hookLogs[0]['context']['verdict_before_notebooklm']);
    }

    public function testFeatureFlagOnRejectVerdictSkipsHook(): void
    {
        $this->settings->method('getBool')
            ->with('notebooklm.factcheck.enabled', false)
            ->willReturn(true);
        $this->settings->method('get')->willReturnArgument(1);
        $this->tierResolver->method('isEnabled')->willReturn(false);

        $graph = $this->makeGraph(
            chains: 2,
            alignments: ['kremlin_aligned', 'wire_neutral'],
            tiers: ['2' => 2],
        );
        $signal = $this->makeSignal(1, 'Știre neverificată');

        $this->gate->rule($graph, [$signal]);

        $this->assertEmpty(
            $this->logger->findByChannel('verification_notebooklm_would_invoke'),
        );
    }

    public function testFeatureFlagOnFlashWithAttributionNeverEmitsHook(): void
    {
        $this->settings->method('getBool')
            ->with('notebooklm.factcheck.enabled', false)
            ->willReturn(true);
        $this->settings->method('get')->willReturnArgument(1);
        $this->tierResolver->method('isEnabled')->willReturn(false);

        $graph = $this->makeGraph(1, ['wire_neutral'], ['1' => 1]);
        // Topic word present but verdict is FLASH_WITH_ATTRIBUTION, not YELLOW.
        $signal = $this->makeSignal(1, 'Alegeri parlamentare confirmate');

        $this->gate->rule($graph, [$signal]);

        $this->assertEmpty(
            $this->logger->findByChannel('verification_notebooklm_would_invoke'),
            'FLASH_WITH_ATTRIBUTION must never trigger NotebookLM hook, even with topic word.',
        );
    }

    /**
     * @param list<string> $alignments
     * @param array<string, int> $tiers
     */
    private function makeGraph(int $chains, array $alignments, array $tiers): ClaimOriginGraph
    {
        return new ClaimOriginGraph(
            topicHash: 'stub-topic',
            claimHash: 'stub-claim',
            nodes: [],
            edges: [],
            alignmentClusters: $alignments,
            independentChains: $chains,
            tierDistribution: $tiers,
        );
    }

    private function makeSignal(int $id, string $title, ?string $summary = null): SourceSignal
    {
        $source = new Source();
        $source->setName('Source-' . $id);

        $vs = new VerifiedSource(
            slug: 'stub-' . $id,
            tier: 1,
            editorialAlignment: EditorialAlignment::WIRE_NEUTRAL,
            trustScoreBaseline: '0.80',
            source: $source,
        );

        $signal = new SourceSignal(
            verifiedSource: $vs,
            sourceUrl: 'https://example.com/' . $id,
            title: $title,
            rawContentHash: str_repeat((string) ($id % 10), 64),
        );
        if ($summary !== null) {
            $signal->setRawSummary($summary);
        }

        return $signal;
    }
}

/**
 * Minimal PSR-3 logger that records every entry for later inspection.
 */
final class CapturingLogger extends AbstractLogger
{
    /** @var list<array{level: mixed, channel: string, context: array<string, mixed>}> */
    private array $records = [];

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = [
            'level' => $level,
            'channel' => (string) $message,
            'context' => $context,
        ];
    }

    /**
     * @return list<array{level: mixed, channel: string, context: array<string, mixed>}>
     */
    public function findByChannel(string $channel): array
    {
        return array_values(array_filter(
            $this->records,
            static fn (array $r): bool => $r['channel'] === $channel,
        ));
    }
}
