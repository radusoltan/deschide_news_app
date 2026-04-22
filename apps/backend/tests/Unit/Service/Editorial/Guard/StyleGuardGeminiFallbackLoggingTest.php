<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Guard;

use App\Agent\AgentDispatcher;
use App\Entity\Article;
use App\Enum\LlmModelTier;
use App\Service\Ai\Exception\LlmUnavailableException;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Editorial\Guard\DiacriticsValidator;
use App\Service\Editorial\Guard\StyleGuard;
use App\Service\Editorial\Llm\LlmInvocationLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * T57.03 follow-up — verify StyleGuard's Gemini fallback logs its own row
 * with Gemini model + inline verdict (same pass/block mapping as the
 * Claude path).
 */
final class StyleGuardGeminiFallbackLoggingTest extends TestCase
{
    #[Test]
    public function geminiFallbackInvokesLogInvocationWithInlineVerdict(): void
    {
        $dispatcher = $this->createMock(AgentDispatcher::class);
        $gemini = $this->createMock(GeminiCliService::class);
        $invocationLogger = $this->createMock(LlmInvocationLogger::class);

        $dispatcher->method('dispatch')->willThrowException(
            new LlmUnavailableException('style_guard', LlmModelTier::HAIKU, LlmModelTier::GEMINI_FLASH, 4),
        );
        $gemini->expects($this->once())->method('execute')->willReturn(
            json_encode(['passed' => false, 'issues' => [[
                'rule' => 'attribution',
                'severity' => 'high',
                'excerpt' => 'afirmație neatribuită',
                'rationale' => 'lipsă sursă',
            ]]], JSON_THROW_ON_ERROR),
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

        $guard = new StyleGuard(
            new DiacriticsValidator(),
            $dispatcher,
            $gemini,
            $invocationLogger,
            $this->createMock(LoggerInterface::class),
        );

        $article = new Article();
        $article->setTitle('Titlu');
        $article->setLead('Lead');
        $article->setContent('Corp neatribuit.');

        $guard->validate($article);

        self::assertSame('style_guard', $capturedAgent);
        self::assertSame('gemini-2.5-flash', $capturedModel);
        self::assertSame('block', $capturedVerdict);
    }
}
