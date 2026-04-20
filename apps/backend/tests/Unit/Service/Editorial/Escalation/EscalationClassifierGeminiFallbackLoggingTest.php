<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Escalation;

use App\Enum\LlmModelTier;
use App\Repository\AppSettingRepository;
use App\Service\Ai\Exception\LlmUnavailableException;
use App\Service\Ai\LlmRetryExecutor;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Editorial\Escalation\EscalationClassifier;
use App\Service\Editorial\Llm\LlmInvocationLogger;
use App\Service\Editorial\Llm\LlmPromptAssembler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * T57.03 follow-up — EscalationClassifier Gemini fallback self-logs with
 * the lowercased category name as verdict (same mapping as the Claude
 * path).
 */
final class EscalationClassifierGeminiFallbackLoggingTest extends TestCase
{
    #[Test]
    public function geminiFallbackInvokesLogInvocationWithInlineVerdict(): void
    {
        $executor = $this->createMock(LlmRetryExecutor::class);
        $gemini = $this->createMock(GeminiCliService::class);
        $settings = $this->createMock(AppSettingRepository::class);
        $invocationLogger = $this->createMock(LlmInvocationLogger::class);

        $settings->method('getBool')->willReturn(true);
        $settings->method('get')->willReturn('v1');

        $executor->method('executeWithRetry')->willThrowException(
            new LlmUnavailableException('escalation_classifier', LlmModelTier::HAIKU, LlmModelTier::GEMINI_FLASH, 4),
        );
        $gemini->expects($this->once())->method('execute')->willReturn(
            json_encode([
                'category' => 'FAMILY_D_CEC_PARTY_LEADERS',
                'is_escalation' => true,
                'confidence' => 0.9,
                'rationale' => 'Boicot electoral',
            ], JSON_THROW_ON_ERROR),
        );

        $capturedAgent = null;
        $capturedModel = null;
        $capturedVerdict = null;
        $invocationLogger->expects($this->once())
            ->method('logInvocation')
            ->willReturnCallback(function (
                string $agentName,
                string $_promptHash,
                int $_durationMs,
                int $_inputTokens,
                int $_outputTokens,
                int $_cacheReadTokens,
                int $_cacheCreationTokens,
                float $_costUsd,
                ?string $model,
                ?string $verdict,
            ) use (&$capturedAgent, &$capturedModel, &$capturedVerdict): ?string {
                $capturedAgent = $agentName;
                $capturedModel = $model;
                $capturedVerdict = $verdict;

                return '01JFXXXXXXXXXXXXXXXXXXXXXX';
            });
        $invocationLogger->expects($this->never())->method('attachVerdict');

        $classifier = new EscalationClassifier(
            $executor,
            $gemini,
            $settings,
            new LlmPromptAssembler(),
            $invocationLogger,
            $this->createMock(LoggerInterface::class),
        );

        $classifier->classify('party leader boycotts election', 'Source');

        self::assertSame('escalation_classifier', $capturedAgent);
        self::assertSame('gemini-2.5-flash', $capturedModel);
        self::assertSame('family_d_cec_party_leaders', $capturedVerdict);
    }
}
