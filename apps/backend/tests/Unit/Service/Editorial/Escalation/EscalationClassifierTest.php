<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Escalation;

use App\Agent\AgentDispatcher;
use App\Agent\Exception\EmergencyHaltException;
use App\Dto\Agent\AgentRequest;
use App\Dto\Agent\AgentResponse;
use App\Enum\Editorial\EscalationCategory;
use App\Enum\LlmModelTier;
use App\Repository\AppSettingRepository;
use App\Service\Ai\Exception\LlmUnavailableException;
use App\Service\Editorial\Escalation\EscalationClassifier;
use App\Service\Editorial\Llm\LlmInvocationLogger;
use App\Service\Editorial\Llm\LlmPromptAssembler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit test for {@see EscalationClassifier} (Sprint 55 T55.8; T57.P8
 * ADR-024 D3 downgrade-only policy retirement).
 *
 * The empirical 50-claim benchmark lives in EscalationClassifierEmpiricalTest
 * and is gated behind RUN_EMPIRICAL_ESCALATION=1. This test covers the
 * deterministic plumbing: enabled-gate, JSON parsing, confidence threshold,
 * fail-open on non-LlmUnavailable errors, enum mapping.
 */
class EscalationClassifierTest extends TestCase
{
    private AgentDispatcher&MockObject $dispatcher;
    private AppSettingRepository&MockObject $appSettingRepository;
    private LlmInvocationLogger&MockObject $invocationLogger;
    private LoggerInterface&MockObject $logger;
    private EscalationClassifier $classifier;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(AgentDispatcher::class);
        $this->appSettingRepository = $this->createMock(AppSettingRepository::class);
        $this->invocationLogger = $this->createMock(LlmInvocationLogger::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Default: enabled=true, role_taxonomy_version=v1.
        $this->appSettingRepository->method('getBool')->willReturn(true);
        $this->appSettingRepository->method('get')->willReturn('v1');

        $this->classifier = new EscalationClassifier(
            $this->dispatcher,
            $this->appSettingRepository,
            new LlmPromptAssembler(),
            $this->invocationLogger,
            $this->logger,
        );
    }

    public function testHappyPathReturnsEscalationCategory(): void
    {
        $this->expectLlmResponse([
            'category' => 'CATEGORY_1_NUCLEAR_WAR',
            'is_escalation' => true,
            'confidence' => 0.95,
            'rationale' => 'Stat nuclear lansează atac nuclear tactic.',
        ]);

        $result = $this->classifier->classify(
            claimText: 'Forțe armate ale unui stat nuclear detonează un dispozitiv tactic.',
            primarySourceTitle: 'Agenție de presă XYZ',
        );

        $this->assertSame(EscalationCategory::CATEGORY_1_NUCLEAR_WAR, $result);
    }

    public function testFamilyCategoryReturnedCorrectly(): void
    {
        $this->expectLlmResponse([
            'category' => 'FAMILY_C_TRANSNISTRIA_GAGAUZIA',
            'is_escalation' => true,
            'confidence' => 0.85,
            'rationale' => 'Referendum unilateral în regiunea separatistă.',
        ]);

        $result = $this->classifier->classify('Tiraspol announces referendum', 'Wire');

        $this->assertSame(EscalationCategory::FAMILY_C_TRANSNISTRIA_GAGAUZIA, $result);
    }

    public function testIsEscalationFalseReturnsNull(): void
    {
        $this->expectLlmResponse([
            'category' => 'NONE',
            'is_escalation' => false,
            'confidence' => 0.3,
            'rationale' => 'Niciuna dintre categorii nu se potrivește.',
        ]);

        $result = $this->classifier->classify('Routine update', 'Wire');

        $this->assertNull($result);
    }

    public function testConfidenceBelowThresholdReturnsNull(): void
    {
        $this->expectLlmResponse([
            'category' => 'CATEGORY_6_CRIMINAL_ACCUSATION',
            'is_escalation' => true,
            'confidence' => 0.5,
            'rationale' => 'Încadrare neclară.',
        ]);

        $this->logger->expects($this->atLeastOnce())
            ->method('info')
            ->with('escalation_classifier_low_confidence', $this->isArray());

        $result = $this->classifier->classify('Ambiguous accusation', 'Source');

        $this->assertNull($result);
    }

    public function testConfidenceAtExactThresholdReturnsCategory(): void
    {
        $this->expectLlmResponse([
            'category' => 'FAMILY_A_CHURCH',
            'is_escalation' => true,
            'confidence' => EscalationClassifier::CONFIDENCE_THRESHOLD,
            'rationale' => 'Sciziune canonică.',
        ]);

        $result = $this->classifier->classify('Patriarchate split', 'Source');

        $this->assertSame(EscalationCategory::FAMILY_A_CHURCH, $result);
    }

    public function testUnknownCategoryNameReturnsNullAndLogs(): void
    {
        $this->expectLlmResponse([
            'category' => 'CATEGORY_99_NONSENSE',
            'is_escalation' => true,
            'confidence' => 0.95,
            'rationale' => 'hallucinated category',
        ]);

        $this->logger->expects($this->atLeastOnce())
            ->method('warning')
            ->with('escalation_classifier_unknown_category', $this->isArray());

        $result = $this->classifier->classify('claim', 'source');

        $this->assertNull($result);
    }

    public function testDisabledClassifierReturnsNullWithoutLlmCall(): void
    {
        $appSettings = $this->createMock(AppSettingRepository::class);
        $appSettings->method('getBool')->willReturn(false);
        $appSettings->method('get')->willReturn('v1');

        $dispatcher = $this->createMock(AgentDispatcher::class);
        $dispatcher->expects($this->never())->method('dispatch');

        $classifier = new EscalationClassifier(
            $dispatcher,
            $appSettings,
            new LlmPromptAssembler(),
            $this->invocationLogger,
            $this->logger,
        );

        $this->assertNull($classifier->classify('x', 'y'));
    }

    /**
     * T57.P8 (ADR-024 D3) — retry exhaust fails closed. Pre-P8 behavior was
     * null-return fail-open on any Throwable from the LLM call; now
     * LlmUnavailable propagates with the uniform D2 log line.
     */
    public function testLlmUnavailablePropagatesWithEditorialReviewLog(): void
    {
        $this->dispatcher->method('dispatch')->willThrowException(
            new LlmUnavailableException(
                agentId: 'escalation_classifier',
                tier: LlmModelTier::HAIKU,
                attempts: 4,
            ),
        );

        $captured = null;
        $this->logger->expects($this->atLeastOnce())
            ->method('warning')
            ->willReturnCallback(function (string $channel, array $payload) use (&$captured): void {
                if ($channel === 'editorial_review_queue') {
                    $captured = $payload;
                }
            });

        $this->expectException(LlmUnavailableException::class);

        try {
            $this->classifier->classify('party leader boycotts', 'source');
        } finally {
            $this->assertIsArray($captured, 'editorial_review_queue log line must be emitted');
            $this->assertSame('escalation_classifier', $captured['agent_id']);
            $this->assertSame('escalation_candidate', $captured['entity_type']);
            $this->assertNull($captured['entity_id']);
            $this->assertSame(['topic_id' => null], $captured['entity_refs']);
            $this->assertNull($captured['invocation_id']);
            $this->assertSame('haiku', $captured['tier_attempted']);
            $this->assertSame('haiku_exhausted', $captured['reason']);
        }
    }

    public function testDecodeErrorPathFailsOpen(): void
    {
        // Non-LlmUnavailable throwables still hit the null-return fail-open
        // branch (decode errors, etc.) — pre-P8 hygiene preserved.
        $this->dispatcher->method('dispatch')->willReturn(new AgentResponse(
            content: 'not a json document',
            agentId: 'escalation_classifier',
            tier: LlmModelTier::HAIKU,
            model: 'claude-haiku-4-5-20251001',
            attempts: 1,
            invocationId: null,
            metrics: null,
        ));

        $this->logger->expects($this->atLeastOnce())
            ->method('warning')
            ->with('escalation_classifier_llm_unavailable', $this->isArray());

        $result = $this->classifier->classify('claim', 'source');

        $this->assertNull($result);
    }

    public function testCodeFencedJsonResponseHandled(): void
    {
        $fenced = "```json\n" . json_encode([
            'category' => 'CATEGORY_2_HEAD_OF_STATE_DEATH',
            'is_escalation' => true,
            'confidence' => 0.9,
            'rationale' => 'Şef de stat.',
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) . "\n```";

        $this->expectRawLlmContent($fenced);

        $result = $this->classifier->classify('head of state dies', 'source');

        $this->assertSame(EscalationCategory::CATEGORY_2_HEAD_OF_STATE_DEATH, $result);
    }

    /**
     * T57.P2c.3 acceptance (d'): CRITICAL — editorial.emergency_halt must
     * propagate past classify() without being swallowed into null fail-open.
     */
    public function testEmergencyHaltExceptionPropagates(): void
    {
        $this->dispatcher->method('dispatch')->willThrowException(
            new EmergencyHaltException('escalation_classifier'),
        );

        $this->invocationLogger->expects($this->never())->method('logInvocation');

        $this->expectException(EmergencyHaltException::class);

        $this->classifier->classify('Stat nuclear lansează atac.', 'Wire');
    }

    /**
     * T57.P2c.3 acceptance (c + AgentRequest shape).
     */
    public function testDispatchReceivesAgentRequestWithHardcodedHaikuTier(): void
    {
        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (AgentRequest $req): bool {
                $this->assertSame('escalation_classifier', $req->agentId);
                $this->assertSame(LlmModelTier::HAIKU, $req->tier);
                $this->assertNotNull($req->systemPrompt);
                $this->assertStringContainsString('clasificatorul editorial', $req->systemPrompt);
                $this->assertCount(1, $req->messages);
                $this->assertSame('user', $req->messages[0]['role']);
                $this->assertNull($req->tierVariant, 'EscalationClassifier has no variant');

                return true;
            }))
            ->willReturn(new AgentResponse(
                content: json_encode([
                    'category' => 'CATEGORY_1_NUCLEAR_WAR',
                    'is_escalation' => true,
                    'confidence' => 0.95,
                    'rationale' => 'test',
                ], JSON_THROW_ON_ERROR),
                agentId: 'escalation_classifier',
                tier: LlmModelTier::HAIKU,
                model: 'claude-haiku-4-5-20251001',
                attempts: 1,
                invocationId: '01JE0Q9ZXJQ8YHZR3S3M7E2P5H',
                metrics: null,
            ));

        $this->classifier->classify('Stat nuclear detonează dispozitiv tactic.', 'Wire');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function expectLlmResponse(array $payload): void
    {
        $this->expectRawLlmContent(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function expectRawLlmContent(string $content): void
    {
        $this->dispatcher->method('dispatch')->willReturn(new AgentResponse(
            content: $content,
            agentId: 'escalation_classifier',
            tier: LlmModelTier::HAIKU,
            model: 'claude-haiku-4-5-20251001',
            attempts: 1,
            invocationId: '01JE0Q9ZXJQ8YHZR3S3M7E2P5H',
            metrics: null,
        ));
    }
}
