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
use App\Service\Editorial\Guard\LegalCategoryDetector;
use App\Service\Editorial\Guard\LegalGuard;
use App\Service\Editorial\Llm\LlmInvocationLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit test for {@see LegalGuard} (Sprint 55 T55.7; T57.P2c.2 AgentDispatcher
 * migration; T57.P8 ADR-024 D3 downgrade-only policy retirement).
 */
class LegalGuardTest extends TestCase
{
    private AgentDispatcher&MockObject $dispatcher;
    private AppSettingRepository&MockObject $appSettingRepository;
    private LlmInvocationLogger&MockObject $invocationLogger;
    private LoggerInterface&MockObject $logger;
    private LegalGuard $guard;

    /** Stores the tier actually passed to the dispatcher for assertions. */
    private ?LlmModelTier $capturedTier = null;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(AgentDispatcher::class);
        $this->appSettingRepository = $this->createMock(AppSettingRepository::class);
        $this->invocationLogger = $this->createMock(LlmInvocationLogger::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->seedTierSettings(generalTier: 'haiku', categ6Tier: 'sonnet');

        $this->guard = new LegalGuard(
            new LegalCategoryDetector(),
            $this->dispatcher,
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

    /**
     * T57.P8 (ADR-024 D3) — retry exhaust fails closed. The pre-P8 fail-open
     * branch is preserved only for non-LlmUnavailable throwables (decode
     * errors, etc.); LlmUnavailable rethrows with the uniform D2 log line.
     */
    public function testLlmUnavailablePropagatesWithEditorialReviewLogOnGeneralArticle(): void
    {
        $this->dispatcher->method('dispatch')->willThrowException(
            new LlmUnavailableException(
                agentId: 'legal_guard',
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

        $article = $this->makeArticle(
            title: 'Agenda legislativă',
            content: 'Comunicat factual despre proiecte noi.',
        );

        try {
            $this->guard->validate($article);
        } finally {
            $this->assertIsArray($captured, 'editorial_review_queue log line must be emitted');
            $this->assertSame('legal_guard', $captured['agent_id']);
            $this->assertSame('article', $captured['entity_type']);
            $this->assertNull($captured['entity_id'], 'Article has no id in this test fixture');
            $this->assertSame([], $captured['entity_refs']);
            $this->assertNull($captured['invocation_id']);
            $this->assertSame('haiku', $captured['tier_attempted']);
            $this->assertSame('haiku_exhausted', $captured['reason']);
        }
    }

    /**
     * T57.P8 — on Category 6 articles the pre-P8 fail-closed-with-
     * ESCALATION_CODE_UNAVAILABLE branch no longer fires for LlmUnavailable
     * (only for other throwables). Instead the exception propagates so the
     * message handler decides the outcome.
     */
    public function testLlmUnavailableOnCategory6ArticleAlsoPropagates(): void
    {
        $this->dispatcher->method('dispatch')->willThrowException(
            new LlmUnavailableException(
                agentId: 'legal_guard',
                tier: LlmModelTier::SONNET,
                attempts: 4,
            ),
        );

        $this->logger->expects($this->atLeastOnce())
            ->method('warning')
            ->with('editorial_review_queue', $this->isArray());

        $this->expectException(LlmUnavailableException::class);

        $article = $this->makeArticle(
            title: 'Ion Popescu acuzat de corupție',
            content: 'Un denunțător afirmă că Ion Popescu ar fi primit mită.',
        );

        $this->guard->validate($article);
    }

    public function testDecodeErrorFailsClosedOnCategory6Article(): void
    {
        // Non-LlmUnavailable throwables (e.g. decode errors on bad payload)
        // keep the pre-P8 fail-closed-on-Cat6 safety net.
        $this->dispatcher->method('dispatch')->willReturn(new AgentResponse(
            content: 'not a json document',
            agentId: 'legal_guard',
            tier: LlmModelTier::SONNET,
            model: 'claude-sonnet-4-6',
            attempts: 1,
            invocationId: null,
            metrics: null,
        ));

        $article = $this->makeArticle(
            title: 'Ion Popescu acuzat de corupție',
            content: 'Un denunțător afirmă că Ion Popescu ar fi primit mită.',
        );

        $part = $this->guard->validate($article);

        $this->assertFalse($part->isPassing());
        $this->assertSame(LegalGuard::ESCALATION_CODE_UNAVAILABLE, $part->escalationCode);
    }

    public function testMissingTierSettingFallsBackToDefaults(): void
    {
        $this->appSettingRepository = $this->createMock(AppSettingRepository::class);
        $this->appSettingRepository->method('find')->willReturn(null);

        $this->guard = new LegalGuard(
            new LegalCategoryDetector(),
            $this->dispatcher,
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
     * convert to a CATEGORY_6 escalation on Cat6 articles.
     */
    public function testEmergencyHaltExceptionPropagatesOnCategory6(): void
    {
        $this->dispatcher->method('dispatch')->willThrowException(
            new EmergencyHaltException('legal_guard'),
        );

        $this->invocationLogger->expects($this->never())->method('logInvocation');

        $this->expectException(EmergencyHaltException::class);

        $article = $this->makeArticle(
            title: 'Ion Popescu acuzat de corupție',
            content: 'Un denunțător afirmă că Ion Popescu ar fi primit mită.',
        );
        $this->guard->validate($article);
    }

    /**
     * T57.P2c.2 acceptance (c + AgentRequest shape).
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
