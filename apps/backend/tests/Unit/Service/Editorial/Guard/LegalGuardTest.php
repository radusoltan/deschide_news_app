<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Guard;

use App\Entity\AppSetting;
use App\Entity\Article;
use App\Enum\LlmModelTier;
use App\Repository\AppSettingRepository;
use App\Service\Ai\Exception\LlmUnavailableException;
use App\Service\Ai\LlmRetryExecutor;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Editorial\Guard\LegalCategoryDetector;
use App\Service\Editorial\Guard\LegalGuard;
use App\Service\Editorial\Llm\LlmInvocationLogger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit test for {@see LegalGuard} (Sprint 55 T55.7).
 *
 * LegalCategoryDetector runs as a real instance because it's pure / stateless;
 * the LLM path and AppSetting lookups are mocked to isolate LegalGuard's
 * severity-mapping + tier-selection + fail-closed/open logic.
 */
class LegalGuardTest extends TestCase
{
    private LlmRetryExecutor&MockObject $llmRetryExecutor;
    private GeminiCliService&MockObject $geminiCliService;
    private AppSettingRepository&MockObject $appSettingRepository;
    private LlmInvocationLogger&MockObject $invocationLogger;
    private LoggerInterface&MockObject $logger;
    private LegalGuard $guard;

    /** Stores the tier actually passed to LlmRetryExecutor for assertions. */
    private ?LlmModelTier $capturedTier = null;

    protected function setUp(): void
    {
        $this->llmRetryExecutor = $this->createMock(LlmRetryExecutor::class);
        $this->geminiCliService = $this->createMock(GeminiCliService::class);
        $this->appSettingRepository = $this->createMock(AppSettingRepository::class);
        $this->invocationLogger = $this->createMock(LlmInvocationLogger::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->seedTierSettings(generalTier: 'haiku', categ6Tier: 'sonnet');

        $this->guard = new LegalGuard(
            new LegalCategoryDetector(),
            $this->llmRetryExecutor,
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
        $this->llmRetryExecutor->method('executeWithRetry')->willThrowException(
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
        $this->llmRetryExecutor->method('executeWithRetry')->willThrowException(
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
        $this->llmRetryExecutor->method('executeWithRetry')->willThrowException(
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
            $this->llmRetryExecutor,
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
     * @param array<string, mixed> $response
     */
    private function expectLlmCall(LlmModelTier $tier, array $response): void
    {
        $this->llmRetryExecutor->method('executeWithRetry')
            ->willReturnCallback(function (string $agentId, array $messages, LlmModelTier $actualTier) use ($response): array {
                $this->capturedTier = $actualTier;

                return [
                    'content' => json_encode($response, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    'agent_id' => $agentId,
                    'tier' => $actualTier->value,
                    'model' => $actualTier->toModelString(),
                    'attempts' => 1,
                    'fallback_detected' => false,
                    'metrics' => null,
                ];
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
