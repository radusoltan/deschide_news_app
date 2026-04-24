<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Guard;

use App\Agent\AgentDispatcher;
use App\Agent\Exception\EmergencyHaltException;
use App\Dto\Agent\AgentRequest;
use App\Dto\Agent\AgentResponse;
use App\Entity\Article;
use App\Enum\LlmModelTier;
use App\Service\Ai\Exception\LlmUnavailableException;
use App\Service\Editorial\Guard\DiacriticsValidator;
use App\Service\Editorial\Guard\StyleGuard;
use App\Service\Editorial\Llm\LlmInvocationLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit test for {@see StyleGuard} (Sprint 55 T55.6; T57.P2c.2 AgentDispatcher
 * migration; T57.P8 ADR-024 D3 downgrade-only policy retirement).
 */
class StyleGuardTest extends TestCase
{
    private AgentDispatcher&MockObject $dispatcher;
    private LlmInvocationLogger&MockObject $invocationLogger;
    private LoggerInterface&MockObject $logger;
    private StyleGuard $guard;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(AgentDispatcher::class);
        $this->invocationLogger = $this->createMock(LlmInvocationLogger::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->guard = new StyleGuard(
            new DiacriticsValidator(),
            $this->dispatcher,
            $this->invocationLogger,
            $this->logger,
        );
    }

    public function testHaikuPassProducesClearVerdict(): void
    {
        $this->dispatcher->method('dispatch')->willReturn($this->buildResponse(
            json_encode(['passed' => true, 'issues' => []], JSON_THROW_ON_ERROR),
        ));

        $article = $this->articleWith('Titlu curat', 'Lead clar', 'Corp cu diacritice: ș, ț.');
        $part = $this->guard->validate($article);

        $this->assertTrue($part->isPassing());
        $this->assertSame([], $part->failures);
        $this->assertSame([], $part->warnings);
    }

    public function testDiacriticsViolationProducesFailure(): void
    {
        $this->dispatcher->method('dispatch')->willReturn($this->buildResponse(
            json_encode(['passed' => true, 'issues' => []], JSON_THROW_ON_ERROR),
        ));

        $article = $this->articleWith('Titlu greşit', 'Lead ok', 'Corp ok.');
        $part = $this->guard->validate($article);

        $this->assertFalse($part->isPassing());
        $this->assertNotEmpty($part->failures);
        $this->assertStringContainsString('[diacritics:title]', $part->failures[0]);
    }

    public function testHighSeverityLlmIssuesProduceFailures(): void
    {
        $this->dispatcher->method('dispatch')->willReturn($this->buildResponse(
            json_encode([
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
        ));

        $article = $this->articleWith('Titlu', 'Lead', 'Corp.');
        $part = $this->guard->validate($article);

        $this->assertFalse($part->isPassing());
        $this->assertCount(1, $part->failures);
        $this->assertStringContainsString('[style:inverted_pyramid:high]', $part->failures[0]);
        $this->assertNull($part->escalationCode);
    }

    public function testLowSeverityLlmIssuesProduceWarnings(): void
    {
        $this->dispatcher->method('dispatch')->willReturn($this->buildResponse(
            json_encode([
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
        ));

        $article = $this->articleWith('Titlu', 'Lead', 'Corp.');
        $part = $this->guard->validate($article);

        $this->assertTrue($part->isPassing());
        $this->assertSame([], $part->failures);
        $this->assertCount(1, $part->warnings);
        $this->assertStringContainsString('[style:sentence_length:low]', $part->warnings[0]);
    }

    /**
     * T57.P8 (ADR-024 D3) — StyleGuard fail-open on LLM exhaust is retired.
     * The exception propagates past validate() with the uniform D2 log line;
     * the pre-P8 pass-with-warning behavior is gone for LlmUnavailable
     * specifically (other throwables still hit the fail-open \\Throwable catch).
     */
    public function testLlmUnavailableFailsClosedWithEditorialReviewLogLine(): void
    {
        $this->dispatcher->method('dispatch')->willThrowException(
            new LlmUnavailableException(
                agentId: 'style_guard',
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

        $article = $this->articleWith('Titlu', 'Lead', 'Corp.');

        try {
            $this->guard->validate($article);
        } finally {
            $this->assertIsArray($captured, 'editorial_review_queue log line must be emitted');
            $this->assertSame('style_guard', $captured['agent_id']);
            $this->assertSame('article', $captured['entity_type']);
            $this->assertNull($captured['entity_id']);
            $this->assertSame([], $captured['entity_refs']);
            $this->assertNull($captured['invocation_id']);
            $this->assertSame('haiku', $captured['tier_attempted']);
            $this->assertSame('haiku_exhausted', $captured['reason']);
        }
    }

    /**
     * Diacritics check runs deterministically BEFORE the LLM branch, so it
     * still records diacritic failures even when the LLM retry exhausts.
     * The LlmUnavailableException then propagates out of validate(); this
     * test verifies both effects.
     */
    public function testDiacriticsRecordedEvenWhenLlmUnavailable(): void
    {
        $this->dispatcher->method('dispatch')->willThrowException(
            new LlmUnavailableException(
                agentId: 'style_guard',
                tier: LlmModelTier::HAIKU,
                attempts: 4,
            ),
        );

        $this->expectException(LlmUnavailableException::class);

        $article = $this->articleWith('Ştire', 'Lead', 'Corp.');
        $this->guard->validate($article);
    }

    public function testDecodeErrorPathFailsOpen(): void
    {
        // Non-LlmUnavailable throwables still hit the fail-open warning
        // branch (decode errors, etc.) — semantic hygiene preserved.
        $this->dispatcher->method('dispatch')->willReturn($this->buildResponse(
            'not a json document',
        ));

        $article = $this->articleWith('Titlu', 'Lead', 'Corp cu ș și ț.');
        $part = $this->guard->validate($article);

        $this->assertTrue($part->isPassing(), 'StyleGuard fails open on non-LLM errors');
        $this->assertNotEmpty($part->warnings);
        $this->assertStringContainsString('LLM check skipped', implode(' ', $part->warnings));
    }

    /**
     * T57.P2c.2 acceptance (d'): CRITICAL — editorial.emergency_halt
     * propagates past validate() without falling through to fail-open.
     */
    public function testEmergencyHaltExceptionPropagates(): void
    {
        $this->dispatcher->method('dispatch')->willThrowException(
            new EmergencyHaltException('style_guard'),
        );

        $this->invocationLogger->expects($this->never())->method('logInvocation');

        $this->expectException(EmergencyHaltException::class);

        $article = $this->articleWith('Titlu', 'Lead', 'Corp cu ș și ț.');
        $this->guard->validate($article);
    }

    /**
     * T57.P2c.2 acceptance (c + AgentRequest shape).
     */
    public function testDispatchReceivesAgentRequestWithHardcodedHaikuTier(): void
    {
        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (AgentRequest $req): bool {
                $this->assertSame('style_guard', $req->agentId);
                $this->assertSame(LlmModelTier::HAIKU, $req->tier);
                $this->assertNotNull($req->systemPrompt);
                $this->assertStringContainsString('redactor-șef', $req->systemPrompt);
                $this->assertCount(1, $req->messages);
                $this->assertSame('user', $req->messages[0]['role']);
                $this->assertNull($req->tierVariant, 'StyleGuard has no variant');

                return true;
            }))
            ->willReturn($this->buildResponse(
                json_encode(['passed' => true, 'issues' => []], JSON_THROW_ON_ERROR),
            ));

        $article = $this->articleWith('Titlu curat', 'Lead clar', 'Corp cu diacritice: ș, ț.');
        $this->guard->validate($article);
    }

    private function buildResponse(string $content): AgentResponse
    {
        return new AgentResponse(
            content: $content,
            agentId: 'style_guard',
            tier: LlmModelTier::HAIKU,
            model: 'claude-haiku-4-5-20251001',
            attempts: 1,
            invocationId: '01JE0Q9ZXJQ8YHZR3S3M7E2P5H',
            metrics: null,
        );
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
