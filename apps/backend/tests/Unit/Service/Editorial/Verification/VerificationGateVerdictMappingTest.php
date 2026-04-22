<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Verification;

use App\Agent\AgentDispatcher;
use App\Dto\Agent\AgentResponse;
use App\Dto\Editorial\ClaimOriginGraph;
use App\Entity\Editorial\SourceSignal;
use App\Entity\Editorial\VerifiedSource;
use App\Entity\Source;
use App\Enum\Editorial\VerdictType;
use App\Enum\EditorialAlignment;
use App\Enum\LlmModelTier;
use App\Repository\AppSettingRepository;
use App\Service\Ai\TierResolver;
use App\Service\Editorial\Llm\LlmInvocationLogger;
use App\Service\Editorial\Verification\VerificationGate;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * T57.03 follow-up — focused coverage for VerificationGate's verdict
 * attachment. VerdictType->value is written on the executor-owned row
 * after the gate's LLM-override resolution. Rule 0 bypass path skips the
 * LLM entirely, so no row is written and no attachVerdict happens.
 */
final class VerificationGateVerdictMappingTest extends TestCase
{
    private const INVOCATION_ID = '01JFXXXXXXXXXXXXXXXXXXXXXX';

    private AgentDispatcher&MockObject $dispatcher;
    private TierResolver&MockObject $tierResolver;
    private AppSettingRepository&MockObject $settings;
    private LlmInvocationLogger&MockObject $invocationLogger;
    private VerificationGate $gate;

    private ?string $capturedVerdict = null;
    private int $attachVerdictCallCount = 0;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(AgentDispatcher::class);
        $this->tierResolver = $this->createMock(TierResolver::class);
        $this->settings = $this->createMock(AppSettingRepository::class);
        $this->invocationLogger = $this->createMock(LlmInvocationLogger::class);

        $this->settings->method('get')
            ->willReturnCallback(static fn (string $key, ?string $default = null): ?string
                => $key === 'editorial.verification.llm_override_confidence' ? '0.8' : $default,
            );

        $this->invocationLogger
            ->method('attachVerdict')
            ->willReturnCallback(function (string $_invocationId, string $verdict): void {
                $this->capturedVerdict = $verdict;
                ++$this->attachVerdictCallCount;
            });

        $this->gate = new VerificationGate(
            $this->dispatcher,
            $this->tierResolver,
            $this->settings,
            $this->invocationLogger,
            new NullLogger(),
        );
    }

    #[Test]
    public function verdictFlashWithAttributionWhenRuleFiresAndLlmConfirms(): void
    {
        $this->enableLlm(LlmModelTier::HAIKU);
        $this->mockSanityResponse(['verdict_sound' => true, 'confidence' => 0.9, 'reasoning' => 'ok']);

        $graph = $this->makeGraph(1, ['wire_neutral'], ['1' => 1]);
        $this->gate->rule($graph, [$this->makeSignal(1, 'Summit UE')]);

        self::assertSame(VerdictType::FLASH_WITH_ATTRIBUTION->value, $this->capturedVerdict);
        self::assertSame('flash_with_attribution', $this->capturedVerdict);
    }

    #[Test]
    public function verdictFlashWithAssertionYellowWhenRuleFiresAndLlmConfirms(): void
    {
        $this->enableLlm(LlmModelTier::SONNET);
        $this->mockSanityResponse(['verdict_sound' => true, 'confidence' => 0.85, 'reasoning' => 'ok']);

        $graph = $this->makeGraph(2, ['kremlin_aligned'], ['1' => 1, '2' => 1]);
        $this->gate->rule($graph, [$this->makeSignal(1, 'Poziție')]);

        self::assertSame('flash_with_assertion_yellow', $this->capturedVerdict);
    }

    #[Test]
    public function verdictFullFlashWhenRuleFiresAndLlmConfirms(): void
    {
        $this->enableLlm(LlmModelTier::HAIKU);
        $this->mockSanityResponse(['verdict_sound' => true, 'confidence' => 0.9, 'reasoning' => 'ok']);

        $graph = $this->makeGraph(
            3,
            ['wire_neutral', 'md_independent_pro_eu', 'kremlin_aligned'],
            ['1' => 2, '2' => 1],
        );
        $this->gate->rule($graph, [$this->makeSignal(1, 'Summit')]);

        self::assertSame('full_flash', $this->capturedVerdict);
    }

    #[Test]
    public function verdictRejectWhenRuleFiresAndLlmConfirms(): void
    {
        $this->enableLlm(LlmModelTier::HAIKU);
        $this->mockSanityResponse(['verdict_sound' => true, 'confidence' => 0.9, 'reasoning' => 'ok']);

        // No tier-1 chain → REJECT.
        $graph = $this->makeGraph(2, ['kremlin_aligned', 'wire_neutral'], ['2' => 2]);
        $this->gate->rule($graph, [$this->makeSignal(1, 'Rumour')]);

        self::assertSame('reject', $this->capturedVerdict);
    }

    #[Test]
    public function verdictEscalateHumanWhenLlmOverridesDownwards(): void
    {
        // Rule → FULL_FLASH; LLM overrides to ESCALATE_HUMAN (downgrade
        // allowed at conf 0.9 ≥ floor 0.8).
        $this->enableLlm(LlmModelTier::HAIKU);
        $this->mockSanityResponse([
            'verdict_sound' => false,
            'confidence' => 0.9,
            'reasoning' => 'actually high-stakes',
            'alternative_verdict' => 'escalate_human',
        ]);

        $graph = $this->makeGraph(
            3,
            ['wire_neutral', 'md_independent_pro_eu', 'kremlin_aligned'],
            ['1' => 2, '2' => 1],
        );
        $this->gate->rule($graph, [$this->makeSignal(1, 'X')]);

        self::assertSame('escalate_human', $this->capturedVerdict);
    }

    #[Test]
    public function rule0BypassDoesNotWriteVerdictBecauseLlmNeverRuns(): void
    {
        // Rule 0 (escalation keyword) bypasses the LLM entirely. No
        // dispatcher call, no baseline row, no attachVerdict.
        $this->dispatcher->expects($this->never())->method('dispatch');
        $this->invocationLogger->expects($this->never())->method('attachVerdict');

        $graph = $this->makeGraph(1, ['wire_neutral'], ['1' => 1]);
        $signal = $this->makeSignal(1, 'Nuclear strike on Kyiv reported');

        $verdict = $this->gate->rule($graph, [$signal]);

        self::assertSame(VerdictType::ESCALATE_HUMAN, $verdict->type);
        self::assertNull($this->capturedVerdict, 'no attachVerdict should fire on Rule 0 bypass');
    }

    // -------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------

    private function enableLlm(LlmModelTier $tier): void
    {
        $this->tierResolver->method('isEnabled')->willReturn(true);
        $this->tierResolver->method('resolve')->willReturn($tier);
    }

    /** @param array<string, mixed> $sanity */
    private function mockSanityResponse(array $sanity): void
    {
        $this->dispatcher->method('dispatch')->willReturn(new AgentResponse(
            content: json_encode($sanity, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            agentId: VerificationGate::AGENT_ID,
            tier: LlmModelTier::HAIKU,
            model: 'claude-haiku-4-5-20251001',
            attempts: 1,
            invocationId: self::INVOCATION_ID,
            metrics: null,
        ));
    }

    /**
     * @param list<string>        $alignments
     * @param array<string, int>  $tiers
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

    private function makeSignal(int $id, string $title): SourceSignal
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

        return new SourceSignal(
            verifiedSource: $vs,
            sourceUrl: 'https://example.com/' . $id,
            title: $title,
            rawContentHash: str_repeat((string) ($id % 10), 64),
        );
    }
}
