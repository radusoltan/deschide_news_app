<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Verification;

use App\Dto\Editorial\ClaimOriginGraph;
use App\Entity\Editorial\SourceSignal;
use App\Entity\Editorial\VerifiedSource;
use App\Entity\Source;
use App\Enum\Editorial\VerdictType;
use App\Enum\EditorialAlignment;
use App\Enum\LlmModelTier;
use App\Repository\AppSettingRepository;
use App\Service\Ai\LlmRetryExecutor;
use App\Service\Ai\TierResolver;
use App\Service\Editorial\Llm\LlmInvocationLogger;
use App\Service\Editorial\Verification\VerificationGate;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class VerificationGateTest extends TestCase
{
    private LlmRetryExecutor&MockObject $executor;
    private TierResolver&MockObject $tierResolver;
    private AppSettingRepository&MockObject $settings;
    private LlmInvocationLogger&MockObject $invocationLogger;
    private VerificationGate $gate;

    protected function setUp(): void
    {
        $this->executor = $this->createMock(LlmRetryExecutor::class);
        $this->tierResolver = $this->createMock(TierResolver::class);
        $this->settings = $this->createMock(AppSettingRepository::class);
        $this->invocationLogger = $this->createMock(LlmInvocationLogger::class);

        $this->settings->method('get')
            ->willReturnCallback(static function (string $key, ?string $default = null): ?string {
                return match ($key) {
                    'editorial.verification.llm_override_confidence' => '0.8',
                    default => $default,
                };
            });

        $this->gate = new VerificationGate(
            $this->executor,
            $this->tierResolver,
            $this->settings,
            $this->invocationLogger,
            new NullLogger(),
        );
    }

    public function testRule1SingleChainTier1ProducesFlashWithAttribution(): void
    {
        $graph = $this->makeGraph(
            chains: 1,
            alignments: ['wire_neutral'],
            tiers: ['1' => 1],
        );

        $this->disableLlmSanity();

        $verdict = $this->gate->rule($graph, [$this->makeSignal(1, 'Summit UE')]);

        $this->assertSame(VerdictType::FLASH_WITH_ATTRIBUTION, $verdict->type);
        $this->assertTrue($verdict->llmSanitySkipped);
    }

    public function testRule2SameAlignmentMultiChainProducesAssertionYellow(): void
    {
        $graph = $this->makeGraph(
            chains: 2,
            alignments: ['kremlin_aligned'],
            tiers: ['1' => 1, '2' => 1],
        );

        $this->disableLlmSanity();

        $verdict = $this->gate->rule($graph, [$this->makeSignal(1, 'Poziție')]);

        $this->assertSame(VerdictType::FLASH_WITH_ASSERTION_YELLOW, $verdict->type);
    }

    public function testRule3DiverseAlignmentProducesFullFlash(): void
    {
        $graph = $this->makeGraph(
            chains: 3,
            alignments: ['wire_neutral', 'md_independent_pro_eu', 'kremlin_aligned'],
            tiers: ['1' => 2, '2' => 1],
        );

        $this->disableLlmSanity();

        $verdict = $this->gate->rule($graph, [$this->makeSignal(1, 'Summit')]);

        $this->assertSame(VerdictType::FULL_FLASH, $verdict->type);
    }

    public function testNoTier1ProducesReject(): void
    {
        $graph = $this->makeGraph(
            chains: 2,
            alignments: ['kremlin_aligned', 'wire_neutral'],
            tiers: ['2' => 2],
        );

        $this->disableLlmSanity();

        $verdict = $this->gate->rule($graph, [$this->makeSignal(1, 'Rumour')]);

        $this->assertSame(VerdictType::REJECT, $verdict->type);
    }

    public function testRule0EscalationKeywordBypassesLlm(): void
    {
        $graph = $this->makeGraph(
            chains: 3,
            alignments: ['wire_neutral', 'kremlin_aligned'],
            tiers: ['1' => 1, '2' => 2],
        );

        // LLM must NOT be called when escalation keyword matches.
        $this->executor->expects($this->never())->method('executeWithRetry');

        $signal = $this->makeSignal(
            1,
            'Alegerile anulate în Transnistria',  // matches md_election_integrity
        );

        $verdict = $this->gate->rule($graph, [$signal]);

        $this->assertSame(VerdictType::ESCALATE_HUMAN, $verdict->type);
        $this->assertNotNull($verdict->escalationKeyword);
        $this->assertStringStartsWith('md_election_integrity:', $verdict->escalationKeyword);
    }

    public function testEscalationDowngradedWhenSignalIsAttributedDeclaration(): void
    {
        $graph = $this->makeGraph(
            chains: 1,
            alignments: ['md_government'],
            tiers: ['1' => 1],
        );

        $this->executor->expects($this->never())->method('executeWithRetry');

        $signal = $this->makeSignal(
            1,
            'Dodon: «Alegerile au fost trucate»',  // quoted declaration
            summary: 'Fostul președinte a declarat că alegerile au fost trucate.',
        );

        $verdict = $this->gate->rule($graph, [$signal]);

        $this->assertSame(
            VerdictType::FLASH_WITH_ATTRIBUTION,
            $verdict->type,
            'Attributed declaration in quotes should downgrade escalate to flash_with_attribution.',
        );
        $this->assertNotNull($verdict->escalationKeyword);
    }

    public function testNonAttributedAssertionStillEscalates(): void
    {
        $graph = $this->makeGraph(
            chains: 1,
            alignments: ['wire_neutral'],
            tiers: ['1' => 1],
        );

        $this->executor->expects($this->never())->method('executeWithRetry');

        // No colon-quote pattern → editorial assertion, not attributed declaration.
        $signal = $this->makeSignal(
            1,
            'Alegeri anulate după dezvăluiri',
            summary: 'Documente obținute de Deschide arată că alegerile vor fi anulate.',
        );

        $verdict = $this->gate->rule($graph, [$signal]);

        $this->assertSame(VerdictType::ESCALATE_HUMAN, $verdict->type);
    }

    public function testEchoChamberWithoutTier1Rejects(): void
    {
        // ADR-020 D3 after T54.8 graph collapse: 3 kremlin sources in a
        // cycle collapse to 1 chain + 1 alignment. Without a tier-1
        // primary, Rule 1 does not fire and the verdict is REJECT — the
        // editorially correct outcome for echo-chamber-only coverage.
        $graph = $this->makeGraph(
            chains: 1,  // collapsed
            alignments: ['kremlin_aligned'],  // single cluster
            tiers: ['2' => 3],  // all tier 2
        );

        $this->disableLlmSanity();

        $verdict = $this->gate->rule($graph, [$this->makeSignal(1, 'Poziție Kremlin')]);

        $this->assertSame(
            VerdictType::REJECT,
            $verdict->type,
            'Echo chamber of tier-2 kremlin sources without a tier-1 primary must REJECT.',
        );
    }

    public function testEchoChamberWithTier1SurfacesAsFlashWithAttribution(): void
    {
        // If the echo chamber includes kremlin-official (tier 1), it collapses
        // to 1 chain + 1 alignment + 1 tier-1. Rule 1 applies →
        // FLASH_WITH_ATTRIBUTION (but the attribution makes clear it's a
        // single-alignment claim — the editorial caveat is baked into the
        // "with_attribution" label).
        $graph = $this->makeGraph(
            chains: 1,
            alignments: ['kremlin_aligned'],
            tiers: ['1' => 1, '2' => 2],
        );

        $this->disableLlmSanity();

        $verdict = $this->gate->rule($graph, [$this->makeSignal(1, 'Declarație oficială')]);

        $this->assertSame(VerdictType::FLASH_WITH_ATTRIBUTION, $verdict->type);
    }

    public function testLlmUnavailableFallsBackToRuleVerdict(): void
    {
        $graph = $this->makeGraph(
            chains: 1,
            alignments: ['wire_neutral'],
            tiers: ['1' => 1],
        );

        $this->tierResolver->method('isEnabled')->willReturn(true);
        $this->tierResolver->method('resolve')->willReturn(LlmModelTier::HAIKU);

        $this->executor->method('executeWithRetry')
            ->willThrowException(new \RuntimeException('transport kaboom'));

        $verdict = $this->gate->rule($graph, [$this->makeSignal(1, 'Titlu normal')]);

        $this->assertSame(VerdictType::FLASH_WITH_ATTRIBUTION, $verdict->type);
        $this->assertTrue($verdict->llmSanitySkipped);
    }

    public function testLlmOverrideDemotesVerdictWhenHighConfidence(): void
    {
        $graph = $this->makeGraph(
            chains: 2,
            alignments: ['kremlin_aligned'],
            tiers: ['1' => 1, '2' => 1],
        );

        $this->tierResolver->method('isEnabled')->willReturn(true);
        $this->tierResolver->method('resolve')->willReturn(LlmModelTier::SONNET);

        $this->executor->method('executeWithRetry')->willReturn([
            'content' => '{"verdict_sound":false,"confidence":0.9,"reasoning":"ambele surse citează aceeași agenție","alternative_verdict":"reject"}',
            'agent_id' => 'verification_gate',
            'tier' => 'sonnet',
            'model' => 'claude-sonnet-4-6',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

        $verdict = $this->gate->rule($graph, [$this->makeSignal(1, 'Știre neutrală')]);

        $this->assertSame(VerdictType::REJECT, $verdict->type);
        $this->assertTrue($verdict->llmOverride);
        $this->assertSame(0.9, $verdict->confidence);
    }

    public function testLlmUpgradeRejectedEvenWithHighConfidence(): void
    {
        // Rule verdict = REJECT (rank 0). LLM suggests FULL_FLASH (rank 4) at
        // confidence 0.95 — this is an UPGRADE attempt which is forbidden
        // under the downgrade-only policy. Rule verdict kept.
        $graph = $this->makeGraph(
            chains: 2,
            alignments: ['kremlin_aligned', 'wire_neutral'],
            tiers: ['2' => 2], // no tier-1 → REJECT
        );

        $this->tierResolver->method('isEnabled')->willReturn(true);
        $this->tierResolver->method('resolve')->willReturn(LlmModelTier::HAIKU);

        $this->executor->method('executeWithRetry')->willReturn([
            'content' => '{"verdict_sound":false,"confidence":0.95,"reasoning":"actually high confidence","alternative_verdict":"full_flash"}',
            'agent_id' => 'verification_gate',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

        $verdict = $this->gate->rule($graph, [$this->makeSignal(1, 'Știre neclară')]);

        $this->assertSame(
            VerdictType::REJECT,
            $verdict->type,
            'LLM upgrade attempt from REJECT to FULL_FLASH must be rejected even at high confidence.',
        );
        $this->assertFalse($verdict->llmOverride);
    }

    public function testLlmDowngradeAcceptedAtHighConfidence(): void
    {
        // Rule verdict = FULL_FLASH (rank 4). LLM suggests YELLOW (rank 2) at
        // 0.9 — downgrade direction, accepted.
        $graph = $this->makeGraph(
            chains: 3,
            alignments: ['wire_neutral', 'md_independent_pro_eu', 'kremlin_aligned'],
            tiers: ['1' => 2, '2' => 1],
        );

        $this->tierResolver->method('isEnabled')->willReturn(true);
        $this->tierResolver->method('resolve')->willReturn(LlmModelTier::HAIKU);

        $this->executor->method('executeWithRetry')->willReturn([
            'content' => '{"verdict_sound":false,"confidence":0.9,"reasoning":"overlap actually","alternative_verdict":"flash_with_assertion_yellow"}',
            'agent_id' => 'verification_gate',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

        $verdict = $this->gate->rule($graph, [$this->makeSignal(1, 'X')]);

        $this->assertSame(VerdictType::FLASH_WITH_ASSERTION_YELLOW, $verdict->type);
        $this->assertTrue($verdict->llmOverride);
    }

    public function testLlmOverrideIgnoredWhenConfidenceBelowFloor(): void
    {
        $graph = $this->makeGraph(
            chains: 1,
            alignments: ['wire_neutral'],
            tiers: ['1' => 1],
        );

        $this->tierResolver->method('isEnabled')->willReturn(true);
        $this->tierResolver->method('resolve')->willReturn(LlmModelTier::HAIKU);

        $this->executor->method('executeWithRetry')->willReturn([
            'content' => '{"verdict_sound":false,"confidence":0.5,"reasoning":"not sure","alternative_verdict":"reject"}',
            'agent_id' => 'verification_gate',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

        $verdict = $this->gate->rule($graph, [$this->makeSignal(1, 'X')]);

        // Confidence 0.5 < floor 0.8 → no override, rule-based verdict kept.
        $this->assertSame(VerdictType::FLASH_WITH_ATTRIBUTION, $verdict->type);
        $this->assertFalse($verdict->llmOverride);
    }

    public function testYellowVerdictRoutesToSonnet(): void
    {
        $graph = $this->makeGraph(
            chains: 2,
            alignments: ['kremlin_aligned'],
            tiers: ['1' => 1, '2' => 1],
        );

        $this->tierResolver->method('isEnabled')->willReturn(true);
        $this->tierResolver->expects($this->once())
            ->method('resolve')
            ->with(VerificationGate::AGENT_ID, 'model_tier_conflict')
            ->willReturn(LlmModelTier::SONNET);

        $this->executor->method('executeWithRetry')->willReturn([
            'content' => '{"verdict_sound":true,"confidence":0.85,"reasoning":"coerent"}',
            'agent_id' => 'verification_gate',
            'tier' => 'sonnet',
            'model' => 'claude-sonnet-4-6',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

        $verdict = $this->gate->rule($graph, [$this->makeSignal(1, 'X')]);

        $this->assertSame(VerdictType::FLASH_WITH_ASSERTION_YELLOW, $verdict->type);
    }

    public function testFullFlashRoutesToHaikuSimple(): void
    {
        $graph = $this->makeGraph(
            chains: 3,
            alignments: ['wire_neutral', 'md_independent_pro_eu', 'kremlin_aligned'],
            tiers: ['1' => 2, '2' => 1],
        );

        $this->tierResolver->method('isEnabled')->willReturn(true);
        $this->tierResolver->expects($this->once())
            ->method('resolve')
            ->with(VerificationGate::AGENT_ID, 'model_tier_simple')
            ->willReturn(LlmModelTier::HAIKU);

        $this->executor->method('executeWithRetry')->willReturn([
            'content' => '{"verdict_sound":true,"confidence":0.9,"reasoning":"coerent"}',
            'agent_id' => 'verification_gate',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

        $verdict = $this->gate->rule($graph, [$this->makeSignal(1, 'X')]);

        $this->assertSame(VerdictType::FULL_FLASH, $verdict->type);
    }

    public function testWarKeywordEscalates(): void
    {
        $graph = $this->makeGraph(1, ['wire_neutral'], ['1' => 1]);
        $this->executor->expects($this->never())->method('executeWithRetry');

        $signal = $this->makeSignal(1, 'Nuclear strike on Kyiv reported');

        $verdict = $this->gate->rule($graph, [$signal]);

        $this->assertSame(VerdictType::ESCALATE_HUMAN, $verdict->type);
        $this->assertStringStartsWith('war_adjacent:', $verdict->escalationKeyword ?? '');
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

    private function disableLlmSanity(): void
    {
        $this->tierResolver->method('isEnabled')
            ->with(VerificationGate::AGENT_ID)
            ->willReturn(false);
        $this->executor->expects($this->never())->method('executeWithRetry');
    }
}
