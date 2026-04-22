<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Guard;

use App\Agent\AgentDispatcher;
use App\Entity\AppSetting;
use App\Entity\Article;
use App\Enum\LlmModelTier;
use App\Repository\AppSettingRepository;
use App\Service\Ai\Exception\LlmUnavailableException;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Editorial\Guard\LegalCategoryDetector;
use App\Service\Editorial\Guard\LegalGuard;
use App\Service\Editorial\Llm\LlmInvocationLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * T57.03 follow-up — verify the Gemini-fallback path self-logs to
 * LlmAgentCallLog with the Gemini model label and the same verdict
 * string mapping as the Claude path (cost-parity for SV-1 rollup).
 */
final class LegalGuardGeminiFallbackLoggingTest extends TestCase
{
    #[Test]
    public function geminiFallbackInvokesLogInvocationWithInlineVerdict(): void
    {
        $dispatcher = $this->createMock(AgentDispatcher::class);
        $gemini = $this->createMock(GeminiCliService::class);
        $settings = $this->createMock(AppSettingRepository::class);
        $invocationLogger = $this->createMock(LlmInvocationLogger::class);

        $settings->method('find')->willReturnCallback(
            static fn (string $key): ?AppSetting => match ($key) {
                'agent.legal_guard.model_tier_general' => new AppSetting($key, 'haiku'),
                'agent.legal_guard.model_tier_categ6' => new AppSetting($key, 'sonnet'),
                default => null,
            },
        );

        // Force Claude tier exhaustion → gate falls back to Gemini.
        $dispatcher->method('dispatch')->willThrowException(
            new LlmUnavailableException('legal_guard', LlmModelTier::HAIKU, LlmModelTier::GEMINI_FLASH, 4),
        );
        $gemini->expects($this->once())->method('execute')->willReturn(
            json_encode(['passed' => false, 'risks' => [[
                'type' => 'unverified_claim',
                'severity' => 'medium',
                'excerpt' => '...',
                'rationale' => 'fără sursă',
            ]]], JSON_THROW_ON_ERROR),
        );

        // Assert logInvocation fires with the Gemini fallback model + verdict
        // derived from the decoded payload (block_medium because severity=medium).
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
        // attachVerdict must NOT fire — the verdict is set at insert time.
        $invocationLogger->expects($this->never())->method('attachVerdict');

        $guard = new LegalGuard(
            new LegalCategoryDetector(),
            $dispatcher,
            $gemini,
            $settings,
            $invocationLogger,
            $this->createMock(LoggerInterface::class),
        );

        $article = new Article();
        $article->setTitle('Analiza pieței');
        $article->setContent('Comentariu cu afirmație neverificată.');

        $guard->validate($article);

        self::assertSame('legal_guard', $capturedAgent);
        self::assertSame('gemini-2.5-flash', $capturedModel);
        self::assertSame('block_medium', $capturedVerdict);
    }
}
