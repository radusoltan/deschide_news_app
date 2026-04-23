<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Guard;

use App\Agent\AgentDispatcher;
use App\Dto\Agent\AgentResponse;
use App\Entity\Article;
use App\Enum\LlmModelTier;
use App\Service\Editorial\Guard\DiacriticsValidator;
use App\Service\Editorial\Guard\StyleGuard;
use App\Service\Editorial\Llm\LlmInvocationLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * T57.03 follow-up — focused coverage for {@see StyleGuard::mapVerdict()}.
 * Verdict is a binary pass/block (per ADR-020 D8 StyleGuard has no
 * escalation ladder).
 */
final class StyleGuardVerdictMappingTest extends TestCase
{
    private const INVOCATION_ID = '01JFXXXXXXXXXXXXXXXXXXXXXX';

    private AgentDispatcher&MockObject $dispatcher;
    private LlmInvocationLogger&MockObject $invocationLogger;
    private StyleGuard $guard;

    private ?string $capturedVerdict = null;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(AgentDispatcher::class);
        $this->invocationLogger = $this->createMock(LlmInvocationLogger::class);

        $this->invocationLogger
            ->method('attachVerdict')
            ->willReturnCallback(function (string $_invocationId, string $verdict): void {
                $this->capturedVerdict = $verdict;
            });

        $this->guard = new StyleGuard(
            new DiacriticsValidator(),
            $this->dispatcher,
            $this->invocationLogger,
            $this->createMock(LoggerInterface::class),
        );
    }

    #[Test]
    public function verdictPassWhenNoStyleIssues(): void
    {
        $this->mockLlmResponse(['passed' => true, 'issues' => []]);

        $this->guard->validate($this->makeArticle('Titlu curat', 'Lead clar', 'Corp ok cu ș și ț.'));

        self::assertSame('pass', $this->capturedVerdict);
    }

    #[Test]
    public function verdictPassWhenOnlyLowSeverityIssues(): void
    {
        // Low severity → warnings only; verdict still 'pass' because no
        // medium/high issue appears in the decoded payload.
        $this->mockLlmResponse(['passed' => true, 'issues' => [[
            'rule' => 'sentence_length',
            'severity' => 'low',
            'excerpt' => '...',
            'rationale' => 'puțin prea lungă',
        ]]]);

        $this->guard->validate($this->makeArticle('Titlu corect', 'Lead', 'Corp ok.'));

        self::assertSame('pass', $this->capturedVerdict);
    }

    #[Test]
    public function verdictBlockWhenMediumSeverityIssue(): void
    {
        $this->mockLlmResponse(['passed' => false, 'issues' => [[
            'rule' => 'inverted_pyramid',
            'severity' => 'medium',
            'excerpt' => 'Titlu vag',
            'rationale' => 'esența lipsește',
        ]]]);

        $this->guard->validate($this->makeArticle('Titlu vag', 'Lead', 'Corp.'));

        self::assertSame('block', $this->capturedVerdict);
    }

    #[Test]
    public function verdictBlockWhenHighSeverityIssue(): void
    {
        $this->mockLlmResponse(['passed' => false, 'issues' => [[
            'rule' => 'attribution',
            'severity' => 'high',
            'excerpt' => 'afirmație neatribuită',
            'rationale' => 'lipsă sursă',
        ]]]);

        $this->guard->validate($this->makeArticle('Titlu', 'Lead', 'Corp neatribuit.'));

        self::assertSame('block', $this->capturedVerdict);
    }

    /** @param array<string, mixed> $payload */
    private function mockLlmResponse(array $payload): void
    {
        $this->dispatcher->method('dispatch')->willReturn(new AgentResponse(
            content: json_encode($payload, JSON_THROW_ON_ERROR),
            agentId: 'style_guard',
            tier: LlmModelTier::HAIKU,
            model: 'claude-haiku-4-5-20251001',
            attempts: 1,
            invocationId: self::INVOCATION_ID,
            metrics: null,
        ));
    }

    private function makeArticle(string $title, string $lead, string $content): Article
    {
        $article = new Article();
        $article->setTitle($title);
        $article->setLead($lead);
        $article->setContent($content);

        return $article;
    }
}
