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
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Editorial\Guard\DiacriticsValidator;
use App\Service\Editorial\Guard\StyleGuard;
use App\Service\Editorial\Llm\LlmInvocationLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit test for {@see StyleGuard} (Sprint 55 T55.6; T57.P2c.2 AgentDispatcher migration).
 */
class StyleGuardTest extends TestCase
{
    private AgentDispatcher&MockObject $dispatcher;
    private GeminiCliService&MockObject $geminiCliService;
    private LlmInvocationLogger&MockObject $invocationLogger;
    private LoggerInterface&MockObject $logger;
    private StyleGuard $guard;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(AgentDispatcher::class);
        $this->geminiCliService = $this->createMock(GeminiCliService::class);
        $this->invocationLogger = $this->createMock(LlmInvocationLogger::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->guard = new StyleGuard(
            new DiacriticsValidator(),
            $this->dispatcher,
            $this->geminiCliService,
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
        // StyleGuard never escalates.
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

    public function testLlmUnavailableFailsOpenWithWarning(): void
    {
        $this->dispatcher->method('dispatch')->willThrowException(
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
        $this->dispatcher->method('dispatch')->willThrowException(
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
        $this->dispatcher->method('dispatch')->willThrowException(
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

    /**
     * T57.P2c.2 acceptance (d'): CRITICAL — editorial.emergency_halt must NOT
     * trigger Gemini fallback. The halt is a deliberate operator decision to
     * stop the pipeline; falling back to an alternate route would defeat the
     * intent. Codifies "halt means halt, not alternate route" at the test
     * level so a future refactor widening catch(LlmUnavailableException) to
     * catch(\RuntimeException) — which would also match EmergencyHaltException
     * since both extend RuntimeException — cannot silently reintroduce the
     * undesired fallback-on-halt behavior.
     *
     * Three asserts per orchestrator directive:
     *   1. GeminiCliService never called (fallback is NOT triggered).
     *   2. Guard re-throws EmergencyHaltException (propagates up to handler).
     *   3. LlmInvocationLogger::logInvocation never called directly (covers
     *      the edge case where Gemini fallback does NOT trigger but the
     *      agent attempts direct Gemini logging anyway).
     */
    public function testEmergencyHaltExceptionPropagatesWithoutTriggeringGeminiFallback(): void
    {
        $this->dispatcher->method('dispatch')->willThrowException(
            new EmergencyHaltException('style_guard'),
        );

        $this->geminiCliService->expects($this->never())->method('execute');
        $this->invocationLogger->expects($this->never())->method('logInvocation');

        $this->expectException(EmergencyHaltException::class);

        $article = $this->articleWith('Titlu', 'Lead', 'Corp cu ș și ț.');
        $this->guard->validate($article);
    }

    /**
     * T57.P2c.2 acceptance (c + AgentRequest shape): the dispatcher receives
     * an AgentRequest carrying agentId=style_guard, the hardcoded HAIKU tier
     * (Pattern-B constant, not TierResolver-driven), the system prompt, and
     * no tierVariant (StyleGuard has no variant — single tier key per
     * ADR-020 D5). Verifies the DTO shape at the dispatcher boundary.
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
