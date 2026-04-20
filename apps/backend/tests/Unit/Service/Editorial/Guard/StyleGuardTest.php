<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Guard;

use App\Entity\Article;
use App\Enum\LlmModelTier;
use App\Service\Ai\Exception\LlmUnavailableException;
use App\Service\Ai\LlmRetryExecutor;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Editorial\Guard\DiacriticsValidator;
use App\Service\Editorial\Guard\StyleGuard;
use App\Service\Editorial\Llm\LlmInvocationLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit test for {@see StyleGuard} (Sprint 55 T55.6).
 */
class StyleGuardTest extends TestCase
{
    private LlmRetryExecutor&MockObject $llmRetryExecutor;
    private GeminiCliService&MockObject $geminiCliService;
    private LlmInvocationLogger&MockObject $invocationLogger;
    private LoggerInterface&MockObject $logger;
    private StyleGuard $guard;

    protected function setUp(): void
    {
        $this->llmRetryExecutor = $this->createMock(LlmRetryExecutor::class);
        $this->geminiCliService = $this->createMock(GeminiCliService::class);
        $this->invocationLogger = $this->createMock(LlmInvocationLogger::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->guard = new StyleGuard(
            new DiacriticsValidator(),
            $this->llmRetryExecutor,
            $this->geminiCliService,
            $this->invocationLogger,
            $this->logger,
        );
    }

    public function testHaikuPassProducesClearVerdict(): void
    {
        $this->llmRetryExecutor->method('executeWithRetry')->willReturn([
            'content' => json_encode(['passed' => true, 'issues' => []], JSON_THROW_ON_ERROR),
            'agent_id' => 'style_guard',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

        $article = $this->articleWith('Titlu curat', 'Lead clar', 'Corp cu diacritice: ș, ț.');
        $part = $this->guard->validate($article);

        $this->assertTrue($part->isPassing());
        $this->assertSame([], $part->failures);
        $this->assertSame([], $part->warnings);
    }

    public function testDiacriticsViolationProducesFailure(): void
    {
        $this->llmRetryExecutor->method('executeWithRetry')->willReturn([
            'content' => json_encode(['passed' => true, 'issues' => []], JSON_THROW_ON_ERROR),
            'agent_id' => 'style_guard',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

        $article = $this->articleWith('Titlu greşit', 'Lead ok', 'Corp ok.');
        $part = $this->guard->validate($article);

        $this->assertFalse($part->isPassing());
        $this->assertNotEmpty($part->failures);
        $this->assertStringContainsString('[diacritics:title]', $part->failures[0]);
    }

    public function testHighSeverityLlmIssuesProduceFailures(): void
    {
        $this->llmRetryExecutor->method('executeWithRetry')->willReturn([
            'content' => json_encode([
                'passed' => false,
                'issues' => [
                    [
                        'rule' => 'inverted_pyramid',
                        'severity' => 'high',
                        'excerpt' => 'Titlu vag',
                        'rationale' => 'Esența lipsește din primele 10 cuvinte.',
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            'agent_id' => 'style_guard',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

        $article = $this->articleWith('Titlu', 'Lead', 'Corp.');
        $part = $this->guard->validate($article);

        $this->assertFalse($part->isPassing());
        $this->assertCount(1, $part->failures);
        $this->assertStringContainsString('[style:inverted_pyramid:high]', $part->failures[0]);
        // StyleGuard never escalates.
        $this->assertNull($part->escalationCode);
    }

    public function testLowSeverityLlmIssuesProduceWarnings(): void
    {
        $this->llmRetryExecutor->method('executeWithRetry')->willReturn([
            'content' => json_encode([
                'passed' => true,
                'issues' => [
                    [
                        'rule' => 'sentence_length',
                        'severity' => 'low',
                        'excerpt' => 'O frază un pic prea lungă.',
                        'rationale' => 'Sub 30 de cuvinte dar la limită.',
                    ],
                ],
            ], JSON_THROW_ON_ERROR),
            'agent_id' => 'style_guard',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

        $article = $this->articleWith('Titlu', 'Lead', 'Corp.');
        $part = $this->guard->validate($article);

        $this->assertTrue($part->isPassing());
        $this->assertSame([], $part->failures);
        $this->assertCount(1, $part->warnings);
        $this->assertStringContainsString('[style:sentence_length:low]', $part->warnings[0]);
    }

    public function testLlmUnavailableFailsOpenWithWarning(): void
    {
        $this->llmRetryExecutor->method('executeWithRetry')->willThrowException(
            new LlmUnavailableException(
                agentId: 'style_guard',
                tier: LlmModelTier::HAIKU,
                fallbackTier: LlmModelTier::GEMINI_FLASH,
                attempts: 4,
            ),
        );
        $this->geminiCliService->method('execute')->willThrowException(
            new \RuntimeException('Gemini CLI process timeout'),
        );

        $article = $this->articleWith('Titlu', 'Lead', 'Corp.');
        $part = $this->guard->validate($article);

        $this->assertTrue($part->isPassing(), 'StyleGuard fails open on LLM unavailability');
        $this->assertNotEmpty($part->warnings);
        $this->assertStringContainsString('LLM check skipped', implode(' ', $part->warnings));
    }

    public function testDiacriticsFailsEvenWhenLlmUnavailable(): void
    {
        // Diacritics runs deterministically — it MUST still catch violations
        // even when the LLM path fails.
        $this->llmRetryExecutor->method('executeWithRetry')->willThrowException(
            new LlmUnavailableException(
                agentId: 'style_guard',
                tier: LlmModelTier::HAIKU,
                fallbackTier: null,
                attempts: 4,
            ),
        );
        $this->geminiCliService->method('execute')->willThrowException(
            new \RuntimeException('fail'),
        );

        $article = $this->articleWith('Ştire', 'Lead', 'Corp.');
        $part = $this->guard->validate($article);

        $this->assertFalse($part->isPassing());
        $this->assertNotEmpty($part->failures);
        $this->assertStringContainsString('[diacritics:title]', $part->failures[0]);
    }

    public function testGeminiFallbackUsedWhenHaikuExhausted(): void
    {
        $this->llmRetryExecutor->method('executeWithRetry')->willThrowException(
            new LlmUnavailableException(
                agentId: 'style_guard',
                tier: LlmModelTier::HAIKU,
                fallbackTier: LlmModelTier::GEMINI_FLASH,
                attempts: 4,
            ),
        );
        $this->geminiCliService->expects($this->once())
            ->method('execute')
            ->willReturn(json_encode(['passed' => true, 'issues' => []], JSON_THROW_ON_ERROR));

        $article = $this->articleWith('Titlu', 'Lead', 'Corp cu ș și ț.');
        $part = $this->guard->validate($article);

        $this->assertTrue($part->isPassing());
    }

    private function articleWith(string $title, string $lead, string $content): Article
    {
        $article = new Article();
        $article->setTitle($title);
        $article->setLead($lead);
        $article->setContent($content);

        return $article;
    }
}
