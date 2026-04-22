<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Guard;

use App\Agent\AgentDispatcher;
use App\Dto\Agent\AgentResponse;
use App\Entity\AppSetting;
use App\Entity\Article;
use App\Enum\LlmModelTier;
use App\Repository\AppSettingRepository;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Editorial\Guard\LegalCategoryDetector;
use App\Service\Editorial\Guard\LegalGuard;
use App\Service\Editorial\Llm\LlmInvocationLogger;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * T57.03 follow-up — focused coverage for {@see LegalGuard::mapVerdict()} via
 * the public `validate()` entry point. Each test drives one branch of the
 * verdict decision tree (pass / block_medium / escalate_cat6) and captures
 * the string that lands on `attachVerdict()`.
 */
final class LegalGuardVerdictMappingTest extends TestCase
{
    private const INVOCATION_ID = '01JFXXXXXXXXXXXXXXXXXXXXXX';

    private AgentDispatcher&MockObject $dispatcher;
    private GeminiCliService&MockObject $geminiCliService;
    private AppSettingRepository&MockObject $settings;
    private LlmInvocationLogger&MockObject $invocationLogger;
    private LegalGuard $guard;

    /** Captures the verdict string that LegalGuard passes to attachVerdict. */
    private ?string $capturedVerdict = null;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(AgentDispatcher::class);
        $this->geminiCliService = $this->createMock(GeminiCliService::class);
        $this->settings = $this->createMock(AppSettingRepository::class);
        $this->invocationLogger = $this->createMock(LlmInvocationLogger::class);

        // Return AppSetting stubs so tier resolver picks the right LlmModelTier.
        $this->settings->method('find')->willReturnCallback(
            fn (string $key): ?AppSetting => match ($key) {
                'agent.legal_guard.model_tier_general' => new AppSetting($key, 'haiku'),
                'agent.legal_guard.model_tier_categ6' => new AppSetting($key, 'sonnet'),
                default => null,
            },
        );

        $this->invocationLogger
            ->method('attachVerdict')
            ->willReturnCallback(function (string $_invocationId, string $verdict): void {
                $this->capturedVerdict = $verdict;
            });

        $this->guard = new LegalGuard(
            new LegalCategoryDetector(),
            $this->dispatcher,
            $this->geminiCliService,
            $this->settings,
            $this->invocationLogger,
            $this->createMock(LoggerInterface::class),
        );
    }

    #[Test]
    public function verdictPassWhenNoBlockingRisks(): void
    {
        $this->mockLlmResponse(['passed' => true, 'risks' => []]);

        $this->guard->validate($this->makeArticle(
            'Guvernul anunță reforme',
            'Comunicat factual despre agenda de reforme.',
        ));

        self::assertSame('pass', $this->capturedVerdict);
    }

    #[Test]
    public function verdictBlockMediumWhenMediumSeverityButNoCat6(): void
    {
        $this->mockLlmResponse([
            'passed' => false,
            'risks' => [[
                'type' => 'unverified_claim',
                'severity' => 'medium',
                'excerpt' => 'afirmație',
                'rationale' => 'neverificată',
            ]],
        ]);

        $this->guard->validate($this->makeArticle(
            'Analiza pieței',
            'Comentariu cu afirmații care ar putea necesita verificare suplimentară.',
        ));

        self::assertSame('block_medium', $this->capturedVerdict);
    }

    #[Test]
    public function verdictEscalateCat6WhenHighSeverityOnNonCat6Article(): void
    {
        $this->mockLlmResponse([
            'passed' => false,
            'risks' => [[
                'type' => 'incitement',
                'severity' => 'high',
                'excerpt' => '...',
                'rationale' => 'risc serios',
            ]],
        ]);

        $this->guard->validate($this->makeArticle(
            'O situație tensionată',
            'Comentariu care ridică semne de risc juridic serios.',
        ));

        self::assertSame('escalate_cat6', $this->capturedVerdict);
    }

    #[Test]
    public function verdictEscalateCat6WhenCategory6DetectedEvenWhenLlmPasses(): void
    {
        // Cat6 pattern hit even if the LLM says passed=true — the keyword
        // detector alone forces escalate_cat6 to match the pipeline's own
        // fail-safe ordering (see LegalGuard::mapResult).
        $this->mockLlmResponse(['passed' => true, 'risks' => []]);

        $this->guard->validate($this->makeArticle(
            'Maria Ionescu acuzată de trafic de persoane',
            'Maria Ionescu este acuzată de trafic de persoane într-un material investigativ.',
        ));

        self::assertSame('escalate_cat6', $this->capturedVerdict);
    }

    /** @param array<string, mixed> $payload */
    private function mockLlmResponse(array $payload): void
    {
        $this->dispatcher->method('dispatch')->willReturn(new AgentResponse(
            content: json_encode($payload, JSON_THROW_ON_ERROR),
            agentId: 'legal_guard',
            tier: LlmModelTier::HAIKU,
            model: 'claude-haiku-4-5-20251001',
            attempts: 1,
            invocationId: self::INVOCATION_ID,
            metrics: null,
        ));
    }

    private function makeArticle(string $title, string $content): Article
    {
        $article = new Article();
        $article->setTitle($title);
        $article->setContent($content);

        return $article;
    }
}
