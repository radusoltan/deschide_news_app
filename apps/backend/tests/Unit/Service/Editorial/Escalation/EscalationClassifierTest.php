<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Escalation;

use App\Entity\AppSetting;
use App\Enum\Editorial\EscalationCategory;
use App\Enum\LlmModelTier;
use App\Repository\AppSettingRepository;
use App\Service\Ai\Exception\LlmUnavailableException;
use App\Service\Ai\LlmRetryExecutor;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Editorial\Escalation\EscalationClassifier;
use App\Service\Editorial\Llm\LlmPromptAssembler;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit test for {@see EscalationClassifier} (Sprint 55 T55.8).
 *
 * The empirical 50-claim benchmark lives in EscalationClassifierEmpiricalTest
 * and is gated behind RUN_EMPIRICAL_ESCALATION=1. This test covers the
 * deterministic plumbing: enabled-gate, JSON parsing, confidence threshold,
 * fail-open behavior, enum mapping.
 */
class EscalationClassifierTest extends TestCase
{
    private LlmRetryExecutor&MockObject $llmRetryExecutor;
    private GeminiCliService&MockObject $geminiCliService;
    private AppSettingRepository&MockObject $appSettingRepository;
    private LoggerInterface&MockObject $logger;
    private EscalationClassifier $classifier;

    protected function setUp(): void
    {
        $this->llmRetryExecutor = $this->createMock(LlmRetryExecutor::class);
        $this->geminiCliService = $this->createMock(GeminiCliService::class);
        $this->appSettingRepository = $this->createMock(AppSettingRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        // Default: enabled=true, role_taxonomy_version=v1.
        $this->appSettingRepository->method('getBool')->willReturn(true);
        $this->appSettingRepository->method('get')->willReturn('v1');

        $this->classifier = new EscalationClassifier(
            $this->llmRetryExecutor,
            $this->geminiCliService,
            $this->appSettingRepository,
            new LlmPromptAssembler(),
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
        // is_escalation=true but confidence 0.5 < 0.7 → null + low-confidence log.
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

        $llm = $this->createMock(LlmRetryExecutor::class);
        $llm->expects($this->never())->method('executeWithRetry');

        $classifier = new EscalationClassifier(
            $llm,
            $this->geminiCliService,
            $appSettings,
            new LlmPromptAssembler(),
            $this->logger,
        );

        $this->assertNull($classifier->classify('x', 'y'));
    }

    public function testLlmUnavailableReturnsNullFailOpen(): void
    {
        $this->llmRetryExecutor->method('executeWithRetry')->willThrowException(
            new LlmUnavailableException('escalation_classifier', LlmModelTier::HAIKU, LlmModelTier::GEMINI_FLASH, 4),
        );
        $this->geminiCliService->method('execute')->willThrowException(
            new \RuntimeException('Gemini timeout'),
        );

        $this->logger->expects($this->atLeastOnce())
            ->method('warning')
            ->with('escalation_classifier_llm_unavailable', $this->isArray());

        $result = $this->classifier->classify('claim', 'source');

        $this->assertNull($result);
    }

    public function testGeminiFallbackUsedWhenHaikuExhausted(): void
    {
        $this->llmRetryExecutor->method('executeWithRetry')->willThrowException(
            new LlmUnavailableException('escalation_classifier', LlmModelTier::HAIKU, LlmModelTier::GEMINI_FLASH, 4),
        );
        $this->geminiCliService->expects($this->once())
            ->method('execute')
            ->willReturn(json_encode([
                'category' => 'FAMILY_D_CEC_PARTY_LEADERS',
                'is_escalation' => true,
                'confidence' => 0.8,
                'rationale' => 'Boicot electoral',
            ], JSON_THROW_ON_ERROR));

        $result = $this->classifier->classify('party leader boycotts', 'source');

        $this->assertSame(EscalationCategory::FAMILY_D_CEC_PARTY_LEADERS, $result);
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
     * @param array<string, mixed> $payload
     */
    private function expectLlmResponse(array $payload): void
    {
        $this->expectRawLlmContent(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private function expectRawLlmContent(string $content): void
    {
        $this->llmRetryExecutor->method('executeWithRetry')->willReturn([
            'content' => $content,
            'agent_id' => 'escalation_classifier',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);
    }
}
