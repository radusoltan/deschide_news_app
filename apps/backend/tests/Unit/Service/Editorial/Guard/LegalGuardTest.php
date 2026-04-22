<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Guard;

use App\Agent\AgentDispatcher;
use App\Agent\Exception\EmergencyHaltException;
use App\Dto\Agent\AgentRequest;
use App\Dto\Agent\AgentResponse;
use App\Entity\AppSetting;
use App\Entity\Article;
use App\Enum\LlmModelTier;
use App\Repository\AppSettingRepository;
use App\Service\Ai\Exception\LlmUnavailableException;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Editorial\Guard\LegalCategoryDetector;
use App\Service\Editorial\Guard\LegalGuard;
use App\Service\Editorial\Llm\LlmInvocationLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit test for {@see LegalGuard} (Sprint 55 T55.7; T57.P2c.2 AgentDispatcher migration).
 *
 * LegalCategoryDetector runs as a real instance because it's pure / stateless;
 * the LLM path and AppSetting lookups are mocked to isolate LegalGuard's
 * severity-mapping + tier-selection + fail-closed/open logic.
 */
class LegalGuardTest extends TestCase
{
    private AgentDispatcher&MockObject $dispatcher;
    private GeminiCliService&MockObject $geminiCliService;
    private AppSettingRepository&MockObject $appSettingRepository;
    private LlmInvocationLogger&MockObject $invocationLogger;
    private LoggerInterface&MockObject $logger;
    private LegalGuard $guard;

    /** Stores the tier actually passed to the dispatcher for assertions. */
    private ?LlmModelTier $capturedTier = null;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(AgentDispatcher::class);
        $this->geminiCliService = $this->createMock(GeminiCliService::class);
        $this->appSettingRepository = $this->createMock(AppSettingRepository::class);
        $this->invocationLogger = $this->createMock(LlmInvocationLogger::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->seedTierSettings(generalTier: 'haiku', categ6Tier: 'sonnet');

        $this->guard = new LegalGuard(
            new LegalCategoryDetector(),
            $this->dispatcher,
            $this->geminiCliService,
            $this->appSettingRepository,
            $this->invocationLogger,
            $this->logger,
        );
    }

    public function testGeneralArticleRoutesThroughHaiku(): void
    {
        $this->expectLlmCall(
            tier: LlmModelTier::HAIKU,
            response: ['passed' => true, 'risks' => []],
        );

        $article = $this->makeArticle(
            title: 'Guvernul anunță reforme',
            content: 'Comunicat factual despre agenda de reforme.',
        );

        $part = $this->guard->validate($article);

        $this->assertTrue($part->isPassing());
        $this->assertSame(LlmModelTier::HAIKU, $this->capturedTier);
    }

    public function testCategory6ArticleRoutesThroughSonnet(): void
    {
        $this->expectLlmCall(
            tier: LlmModelTier::SONNET,
            response: ['passed' => false, 'risks' => [
                ['type' => 'defamation', 'severity' => 'high', 'excerpt' => 'acuzat', 'rationale' => 'fără dosar'],
            ]],
        );

        $article = $this->makeArticle(
            title: 'Ion Popescu este acuzat de corupție',
            content: 'Un denunțător susține că Ion Popescu ar fi primit mită în contextul unei licitații.',
        );

        $part = $this->guard->validate($article);

        $this->assertSame(LlmModelTier::SONNET, $this->capturedTier);
        $this->assertFalse($part->isPassing());
    }

    public function testCategory6AlwaysEscalatesEvenIfLlmSaysPassed(): void
    {
        // Fail-safe: the LLM may disagree with the keyword detector, but the
        // keyword pattern alone triggers ESCALATE_HUMAN per audit D8.
        $this->expectLlmCall(
            tier: LlmModelTier::SONNET,
            response: ['passed' => true, 'risks' => []],
        );

        $article = $this->makeArticle(
            title: 'Maria Ionescu acuzată de trafic de persoane',
            content: 'Maria Ionescu este acuzată de trafic de persoane într-un material investigativ.',
        );

        $part = $this->guard->validate($article);

        $this->assertFalse($part->isPassing());
        $this->assertSame(LegalGuard::ESCALATION_CODE_CATEGORY_6, $part->escalationCode);
    }

    public function testHighSeverityOnGeneralArticleAlsoEscalates(): void
    {
        // A non-Cat6 article can still hit the escalation path if the LLM
        // returns a high-severity risk finding.
        $this->expectLlmCall(
            tier: LlmModelTier::HAIKU,
            response: ['passed' => false, 'risks' => [
                ['type' => 'incitement', 'severity' => 'high', 'excerpt' => '...', 'rationale' => 'risc serios'],
            ]],
        );

        $article = $this->makeArticle(
            title: 'O situație tensionată',
            content: 'Comentariu care ridică semne de risc juridic serios.',
        );

        $part = $this->guard->validate($article);

        $this->assertFalse($part->isPassing());
        $this->assertSame(LegalGuard::ESCALATION_CODE_CATEGORY_6, $part->escalationCode);
    }

    public function testMediumSeverityBlocksWithoutEscalating(): void
    {
        $this->expectLlmCall(
            tier: LlmModelTier::HAIKU,
            response: ['passed' => false, 'risks' => [
                ['type' => 'unverified_claim', 'severity' => 'medium', 'excerpt' => '...', 'rationale' => 'afirmație neverificată'],
            ]],
        );

        $article = $this->makeArticle(
            title: 'Analiza pieței',
            content: 'Comentariu cu afirmații care ar putea necesita verificare suplimentară.',
        );

        $part = $this->guard->validate($article);

        $this->assertFalse($part->isPassing());
        $this->assertNull($part->escalationCode);
        $this->assertNotEmpty($part->failures);
    }

    public function testLowSeverityProducesWarningOnly(): void
    {
        $this->expectLlmCall(
            tier: LlmModelTier::HAIKU,
            response: ['passed' => true, 'risks' => [
                ['type' => 'speculation', 'severity' => 'low', 'excerpt' => '...', 'rationale' => 'observație minoră'],
            ]],
        );

        $article = $this->makeArticle(
            title: 'Perspective economice',
            content: 'Corp cu ton echilibrat și atribuire corectă.',
        );

        $part = $this->guard->validate($article);

        $this->assertTrue($part->isPassing());
        $this->assertNotEmpty($part->warnings);
        $this->assertSame([], $part->failures);
    }

    public function testLlmUnavailableFailsOpenOnGeneralArticle(): void
    {
        $this->dispatcher->method('dispatch')->willThrowException(
            new LlmUnavailableException('legal_guard', LlmModelTier::HAIKU, LlmModelTier::GEMINI_FLASH, 4),
        );
        $this->geminiCliService->method('execute')->willThrowException(
            new \RuntimeException('Gemini CLI timeout'),
        );

        $article = $this->makeArticle(
            title: 'Agenda legislativă',
            content: 'Comunicat factual despre proiecte noi.',
        );

        $part = $this->guard->validate($article);

        $this->assertTrue($part->isPassing(), 'General article fails open on LLM unavailability');
        $this->assertNotEmpty($part->warnings);
        $this->assertNull($part->escalationCode);
    }

    public function testLlmUnavailableFailsClosedOnCategory6Article(): void
    {
        $this->dispatcher->method('dispatch')->willThrowException(
            new LlmUnavailableException('legal_guard', LlmModelTier::SONNET, null, 4),
        );
        $this->geminiCliService->method('execute')->willThrowException(
            new \RuntimeException('Gemini CLI timeout'),
        );

        $article = $this->makeArticle(
            title: 'Ion Popescu acuzat de corupție',
            content: 'Un denunțător afirmă că Ion Popescu ar fi primit mită.',
        );

        $part = $this->guard->validate($article);

        $this->assertFalse($part->isPassing());
        $this->assertSame(LegalGuard::ESCALATION_CODE_UNAVAILABLE, $part->escalationCode);
        $this->assertNotEmpty($part->failures);
    }

    public function testGeminiFallbackUsedWhenPrimaryTierExhausted(): void
    {
        $this->dispatcher->method('dispatch')->willThrowException(
            new LlmUnavailableException('legal_guard', LlmModelTier::HAIKU, LlmModelTier::GEMINI_FLASH, 4),
        );
        $this->geminiCliService->expects($this->once())
            ->method('execute')
            ->willReturn(json_encode(['passed' => true, 'risks' => []], JSON_THROW_ON_ERROR));

        $article = $this->makeArticle(
            title: 'Agenda',
            content: 'Corp neutru.',
        );

        $part = $this->guard->validate($article);

        $this->assertTrue($part->isPassing());
    }

    public function testMissingTierSettingFallsBackToDefaults(): void
    {
        $this->appSettingRepository = $this->createMock(AppSettingRepository::class);
        $this->appSettingRepository->method('find')->willReturn(null);

        $this->guard = new LegalGuard(
            new LegalCategoryDetector(),
            $this->dispatcher,
            $this->geminiCliService,
            $this->appSettingRepository,
            $this->invocationLogger,
            $this->logger,
        );

        $this->expectLlmCall(
            tier: LlmModelTier::HAIKU, // default for general
            response: ['passed' => true, 'risks' => []],
        );

        $this->guard->validate($this->makeArticle('Agenda', 'Corp.'));

        $this->assertSame(LlmModelTier::HAIKU, $this->capturedTier);
    }

    /**
     * T57.P2c.2 acceptance (d'): CRITICAL — editorial.emergency_halt must NOT
     * trigger Gemini fallback AND must NOT convert to a CATEGORY_6 escalation
     * on Cat6 articles (which the generic \\Throwable catch in validate()
     * would otherwise do — silently swallowing the operator's halt signal
     * into what looks like a Cat6 legal-review escalation).
     *
     * Codifies "halt means halt, not alternate route" at the test level.
     * Uses Cat6-triggering article content because LegalGuard's fail-closed
     * behavior on Cat6 is the dangerous path — if a future refactor widened
     * the catch clause or removed the explicit EmergencyHaltException case,
     * the halt would be swallowed into a CATEGORY_6 escalation row,
     * indistinguishable from a genuine Cat6 detection.
     *
     * Three asserts per orchestrator directive:
     *   1. GeminiCliService never called (fallback is NOT triggered).
     *   2. Guard re-throws EmergencyHaltException (propagates up to handler).
     *   3. LlmInvocationLogger::logInvocation never called directly.
     */
    public function testEmergencyHaltExceptionPropagatesWithoutTriggeringGeminiFallbackOnCategory6(): void
    {
        $this->dispatcher->method('dispatch')->willThrowException(
            new EmergencyHaltException('legal_guard'),
        );

        $this->geminiCliService->expects($this->never())->method('execute');
        $this->invocationLogger->expects($this->never())->method('logInvocation');

        $this->expectException(EmergencyHaltException::class);

        // Deliberately Cat6-matching article — exercises the dangerous path
        // where a swallowed halt would become a CATEGORY_6 escalation.
        $article = $this->makeArticle(
            title: 'Ion Popescu acuzat de corupție',
            content: 'Un denunțător afirmă că Ion Popescu ar fi primit mită.',
        );
        $this->guard->validate($article);
    }

    /**
     * T57.P2c.2 acceptance (c + AgentRequest shape): the dispatcher receives
     * an AgentRequest carrying agentId=legal_guard, a tier resolved via
     * direct AppSettings read (Pattern-B Category-6 routing — Sonnet for
     * Cat6, Haiku otherwise), the system prompt, and no tierVariant
     * (LegalGuard uses full AppSettings key switching, not variant suffix
     * semantics — unlike VerificationGate).
     */
    public function testDispatchReceivesAgentRequestWithCategory6ResolvedTier(): void
    {
        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (AgentRequest $req): bool {
                $this->capturedTier = $req->tier;
                $this->assertSame('legal_guard', $req->agentId);
                $this->assertSame(LlmModelTier::SONNET, $req->tier, 'Cat6 article must route to Sonnet');
                $this->assertNotNull($req->systemPrompt);
                $this->assertStringContainsString('consilier juridic', $req->systemPrompt);
                $this->assertNull(
                    $req->tierVariant,
                    'LegalGuard uses full AppSettings key switching, not variant suffix',
                );

                return true;
            }))
            ->willReturn(new AgentResponse(
                content: json_encode(['passed' => false, 'risks' => [
                    ['type' => 'defamation', 'severity' => 'high', 'excerpt' => 'acuzat', 'rationale' => 'fără dosar'],
                ]], JSON_THROW_ON_ERROR),
                agentId: 'legal_guard',
                tier: LlmModelTier::SONNET,
                model: 'claude-sonnet-4-6',
                attempts: 1,
                invocationId: '01JE0Q9ZXJQ8YHZR3S3M7E2P5H',
                metrics: null,
            ));

        $article = $this->makeArticle(
            title: 'Ion Popescu este acuzat de corupție',
            content: 'Un denunțător susține că Ion Popescu ar fi primit mită.',
        );
        $this->guard->validate($article);
    }

    /**
     * @param array<string, mixed> $response
     */
    private function expectLlmCall(LlmModelTier $tier, array $response): void
    {
        $this->dispatcher->method('dispatch')
            ->willReturnCallback(function (AgentRequest $request) use ($response): AgentResponse {
                $this->capturedTier = $request->tier;

                return new AgentResponse(
                    content: json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    agentId: $request->agentId,
                    tier: $request->tier,
                    model: $request->tier->toModelString(),
                    attempts: 1,
                    invocationId: '01JE0Q9ZXJQ8YHZR3S3M7E2P5H',
                    metrics: null,
                );
            });
    }

    private function seedTierSettings(string $generalTier, string $categ6Tier): void
    {
        $this->appSettingRepository->method('find')
            ->willReturnCallback(function (string $key) use ($generalTier, $categ6Tier): ?AppSetting {
                return match ($key) {
                    'agent.legal_guard.model_tier_general' => new AppSetting($key, $generalTier),
                    'agent.legal_guard.model_tier_categ6' => new AppSetting($key, $categ6Tier),
                    default => null,
                };
            });
    }

    private function makeArticle(string $title, string $content): Article
    {
        $article = new Article();
        $article->setTitle($title);
        $article->setContent($content);

        return $article;
    }
}
