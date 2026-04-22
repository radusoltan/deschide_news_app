<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Writer;

use App\Agent\AgentDispatcher;
use App\Agent\Exception\EmergencyHaltException;
use App\Dto\Agent\AgentRequest;
use App\Dto\Agent\AgentResponse;
use App\Dto\Editorial\VerificationVerdict;
use App\Entity\Article;
use App\Entity\Author;
use App\Entity\Category;
use App\Entity\Editorial\SourceSignal;
use App\Entity\Editorial\VerifiedSource;
use App\Entity\Topic;
use App\Enum\ArticleStatus;
use App\Enum\ArticleType;
use App\Enum\Editorial\VerdictType;
use App\Enum\EditorialAlignment;
use App\Enum\LlmModelTier;
use App\Service\Ai\Exception\LlmUnavailableException;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Editorial\Llm\LlmInvocationLogger;
use App\Service\Editorial\Llm\LlmPromptAssembler;
use App\Service\Editorial\Writer\AiAuthorProvider;
use App\Service\Editorial\Writer\FlashWriter;
use App\Service\Editorial\Writer\SignalCategoryResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit test for {@see FlashWriter} (Sprint 55 T55.3; T57.P2c.4 AgentDispatcher migration).
 *
 * The writer orchestrates: LLM call → JSON parse → Category/Author resolution →
 * Article construction → persist → dispatch translations. Every collaborator is
 * mocked; no DB contact.
 */
class FlashWriterTest extends TestCase
{
    private AgentDispatcher&MockObject $dispatcher;
    private GeminiCliService&MockObject $geminiCliService;
    private SignalCategoryResolver&MockObject $categoryResolver;
    private AiAuthorProvider&MockObject $aiAuthorProvider;
    private EntityManagerInterface&MockObject $em;
    private LlmInvocationLogger&MockObject $llmInvocationLogger;
    private LoggerInterface&MockObject $logger;
    private FlashWriter $writer;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(AgentDispatcher::class);
        $this->geminiCliService = $this->createMock(GeminiCliService::class);
        $this->categoryResolver = $this->createMock(SignalCategoryResolver::class);
        $this->aiAuthorProvider = $this->createMock(AiAuthorProvider::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->llmInvocationLogger = $this->createMock(LlmInvocationLogger::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->writer = new FlashWriter(
            $this->dispatcher,
            $this->geminiCliService,
            $this->categoryResolver,
            $this->aiAuthorProvider,
            $this->em,
            $this->llmInvocationLogger,
            new LlmPromptAssembler(),
            $this->logger,
        );
    }

    public function testHaikuHappyPathProducesArticleAndDispatchesTranslations(): void
    {
        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (AgentRequest $req): bool {
                $this->assertSame('flash_writer', $req->agentId);
                $this->assertSame(LlmModelTier::HAIKU, $req->tier);
                $this->assertIsString($req->systemPrompt);

                return true;
            }))
            ->willReturn($this->happyPathAgentResponse());

        $this->geminiCliService->expects($this->never())->method('execute');

        $this->categoryResolver->expects($this->once())
            ->method('resolve')
            ->willReturn($this->mockCategory('politica'));

        $this->aiAuthorProvider->expects($this->once())
            ->method('getOrCreate')
            ->willReturn($this->mockAuthor());

        $persistedArticle = null;
        $this->em->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function (object $entity) use (&$persistedArticle): void {
                $this->assertInstanceOf(Article::class, $entity);
                $persistedArticle = $entity;
            });
        $this->em->expects($this->once())->method('flush');

        $primary = $this->mockSignal(42, 'Titlu sursă', 'Rezumat cu diacritice: Chișinău și Bălți.');
        $verdict = new VerificationVerdict(
            type: VerdictType::FULL_FLASH,
            reasoning: 'Două surse independente cu alignment divers.',
            confidence: 0.92,
        );

        $article = $this->writer->write($primary, [], $verdict);

        $this->assertSame($persistedArticle, $article);
        $this->assertSame(ArticleStatus::NEW, $article->getStatus());
        $this->assertSame(ArticleType::FLASH, $article->getArticleType());
        $this->assertSame('Un exemplu de titlu despre Chișinău', $article->getTitle());
        $this->assertTrue($article->isAiGenerated());
        $this->assertSame(1, $article->getAiSourceCount());
        $this->assertEqualsWithDelta(0.92, $article->getAiConfidenceScore(), 0.001);
        $this->assertSame(1, $article->getRevisionCount());

        // Diacritics preserved — comma-below, never cedilla.
        $this->assertStringContainsString('ș', $article->getContent() ?? '');
        $this->assertStringContainsString('ț', $article->getContent() ?? '');
        $this->assertSame(0, preg_match('/[ŞşŢţ]/u', (string) $article->getContent()));

        // revision_history[0] correctly populated.
        $history = $article->getRevisionHistory();
        $this->assertIsArray($history);
        $this->assertCount(1, $history);
        $this->assertSame(1, $history[0]['rev']);
        $this->assertSame('initial', $history[0]['diff']);
    }

    public function testGeminiFallbackWhenHaikuUnavailable(): void
    {
        $this->dispatcher->method('dispatch')->willThrowException(
            new LlmUnavailableException(
                agentId: 'flash_writer',
                tier: LlmModelTier::HAIKU,
                fallbackTier: LlmModelTier::GEMINI_FLASH,
                attempts: 4,
            ),
        );

        $this->geminiCliService->expects($this->once())
            ->method('execute')
            ->with($this->isString(), $this->isArray())
            ->willReturn($this->happyPathResponse());

        $this->categoryResolver->method('resolve')->willReturn($this->mockCategory('externe'));
        $this->aiAuthorProvider->method('getOrCreate')->willReturn($this->mockAuthor());

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');
        $this->logger->expects($this->atLeastOnce())
            ->method('warning')
            ->with('flash_writer_haiku_unavailable_trying_gemini', $this->isArray());

        $primary = $this->mockSignal(7, 'Semnal wire', 'Sumar');
        $verdict = new VerificationVerdict(VerdictType::FLASH_WITH_ATTRIBUTION, 'OK', confidence: 0.7);

        $article = $this->writer->write($primary, [], $verdict);

        $this->assertInstanceOf(Article::class, $article);
        $this->assertSame(ArticleType::FLASH, $article->getArticleType());
    }

    public function testT5703HaikuPathDelegatesLoggingToExecutor(): void
    {
        // T57.03 (ADR-023 D2) — the Haiku baseline row is written by
        // LlmRetryExecutor (T57.P2c.4: now via AgentDispatcher→executor
        // transitively), not by FlashWriter. FlashWriter no longer calls
        // logInvocation() on the Claude path; the executor handles it on
        // every successful invocation for W' universal coverage. The Gemini
        // fallback path still self-logs (next test).
        $this->dispatcher->method('dispatch')->willReturn($this->happyPathAgentResponse(
            metrics: [
                'input_tokens' => 1024,
                'output_tokens' => 256,
                'cache_read_tokens' => 128,
                'cache_creation_tokens' => 0,
                'cost_usd' => 0.0175,
                'duration_ms' => 1500,
            ],
            invocationId: '01JFXXXXXXXXXXXXXXXXXXXXXX',
        ));
        $this->categoryResolver->method('resolve')->willReturn($this->mockCategory('politica'));
        $this->aiAuthorProvider->method('getOrCreate')->willReturn($this->mockAuthor());

        // Critical assertion: FlashWriter must NOT call logInvocation on the
        // Haiku path. Executor-owned logging replaced this.
        $this->llmInvocationLogger->expects($this->never())->method('logInvocation');

        $primary = $this->mockSignal(101, 'Titlu', 'Sumar');
        $verdict = new VerificationVerdict(VerdictType::FULL_FLASH, 'OK', confidence: 0.9);

        $article = $this->writer->write($primary, [], $verdict);

        $this->assertInstanceOf(Article::class, $article);
    }

    public function testT5609LogsGeminiFallbackInvocationWithZeroSentinels(): void
    {
        // T56.09 — Gemini CLI fallback path does not expose wrapper metrics;
        // the hook must still record a row using wall-clock duration and
        // explicit zeros (not garbage) for token/cost fields.
        $this->dispatcher->method('dispatch')->willThrowException(
            new LlmUnavailableException(
                agentId: 'flash_writer',
                tier: LlmModelTier::HAIKU,
                fallbackTier: LlmModelTier::GEMINI_FLASH,
                attempts: 4,
            ),
        );
        $this->geminiCliService->method('execute')->willReturn($this->happyPathResponse());
        $this->categoryResolver->method('resolve')->willReturn($this->mockCategory('externe'));
        $this->aiAuthorProvider->method('getOrCreate')->willReturn($this->mockAuthor());

        $this->llmInvocationLogger->expects($this->once())
            ->method('logInvocation')
            ->with(
                'flash_writer',
                $this->callback(static fn (string $h): bool => \strlen($h) === 64),
                $this->callback(static fn (int $d): bool => $d >= 0),  // wall-clock, positive
                0,                   // inputTokens sentinel
                0,                   // outputTokens sentinel
                0,                   // cacheReadTokens default
                0,                   // cacheCreationTokens default
                0.0,                 // costUsd default
                'gemini-2.5-flash',  // model fixed from FALLBACK_MODEL constant
                null,
            );

        $primary = $this->mockSignal(102, 'Titlu', 'Sumar');
        $verdict = new VerificationVerdict(VerdictType::FULL_FLASH, 'OK', confidence: 0.7);

        $this->writer->write($primary, [], $verdict);
    }

    public function testThrowsWhenLlmResponseIsNotJson(): void
    {
        $this->dispatcher->method('dispatch')->willReturn(
            $this->buildAgentResponse('Acesta nu este JSON, ci text liber.'),
        );

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('could not decode');

        $primary = $this->mockSignal(1, 'T', 'S');
        $verdict = new VerificationVerdict(VerdictType::FULL_FLASH, 'ok', confidence: 0.9);

        $this->writer->write($primary, [], $verdict);
    }

    public function testStripsCodeFencesFromLlmResponse(): void
    {
        $fenced = "```json\n" . $this->happyPathResponse() . "\n```";

        $this->dispatcher->method('dispatch')->willReturn($this->buildAgentResponse($fenced));

        $this->categoryResolver->method('resolve')->willReturn($this->mockCategory('politica'));
        $this->aiAuthorProvider->method('getOrCreate')->willReturn($this->mockAuthor());

        $primary = $this->mockSignal(13, 'Titlu', 'Sumar');
        $verdict = new VerificationVerdict(VerdictType::FULL_FLASH, 'ok', confidence: 0.9);

        $article = $this->writer->write($primary, [], $verdict);

        $this->assertInstanceOf(Article::class, $article);
        $this->assertNotEmpty($article->getTitle());
    }

    public function testFlashWithAttributionVerdictSetsInternalSummaryFlag(): void
    {
        $this->dispatcher->method('dispatch')->willReturn($this->happyPathAgentResponse());

        $this->categoryResolver->method('resolve')->willReturn($this->mockCategory('externe'));
        $this->aiAuthorProvider->method('getOrCreate')->willReturn($this->mockAuthor());

        $primary = $this->mockSignal(9, 'T', 'S');
        $verdict = new VerificationVerdict(
            VerdictType::FLASH_WITH_ATTRIBUTION,
            'Un singur raportor aliniat ideologic',
            confidence: 0.75,
        );

        $article = $this->writer->write($primary, [], $verdict);

        $summary = $article->getInternalSummary() ?? '';
        $this->assertStringContainsString('flash_with_attribution', $summary);
        $this->assertStringContainsString('[flash_writer]', $summary);
    }

    public function testSupportingSignalsIncrementAiSourceCount(): void
    {
        $this->dispatcher->method('dispatch')->willReturn($this->happyPathAgentResponse());

        $this->categoryResolver->method('resolve')->willReturn($this->mockCategory('politica'));
        $this->aiAuthorProvider->method('getOrCreate')->willReturn($this->mockAuthor());

        $primary = $this->mockSignal(100, 'Primar', 'p');
        $supporting = [
            $this->mockSignal(101, 'Sup1', 's1'),
            $this->mockSignal(102, 'Sup2', 's2'),
            $this->mockSignal(103, 'Sup3', 's3'),
        ];
        $verdict = new VerificationVerdict(VerdictType::FULL_FLASH, 'ok', confidence: 0.9);

        $article = $this->writer->write($primary, $supporting, $verdict);

        // Primary + 3 supporting = 4 distinct sources.
        $this->assertSame(4, $article->getAiSourceCount());
    }

    public function testTopicAssociationWhenTopicPassed(): void
    {
        $this->dispatcher->method('dispatch')->willReturn($this->happyPathAgentResponse());

        $this->categoryResolver->method('resolve')->willReturn($this->mockCategory('politica'));
        $this->aiAuthorProvider->method('getOrCreate')->willReturn($this->mockAuthor());

        $topic = $this->createMock(Topic::class);
        $topic->method('getId')->willReturn(55);

        $primary = $this->mockSignal(1, 't', 's');
        $verdict = new VerificationVerdict(VerdictType::FULL_FLASH, 'ok', confidence: 0.9);

        $article = $this->writer->write($primary, [], $verdict, $topic);

        $this->assertTrue($article->getTopics()->contains($topic));
    }

    /**
     * T57.P2c.4 acceptance (d'): CRITICAL — editorial.emergency_halt must NOT
     * trigger Gemini fallback AND must NOT leave orphan Article rows in the
     * DB. This test codifies Scenario-A persistence safety empirically: the
     * EntityManager's persist() and flush() must NEVER be invoked when halt
     * propagates from the dispatcher.
     *
     * Four asserts per orchestrator directive + Scenario-A codification:
     *   1. GeminiCliService never called (fallback is NOT triggered).
     *   2. LlmInvocationLogger::logInvocation never called (direct Gemini log).
     *   3. EntityManager::persist and flush never called (ZERO orphan rows).
     *   4. Writer re-throws EmergencyHaltException (propagates up to handler).
     *
     * The em->never() assertions convert the observable Scenario-A property
     * (FlashWriter line 105→113 invoke-then-persist, confirmed during P2c.4
     * Discovery) into a test-guaranteed invariant. A future refactor that
     * introduces a pre-LLM shell-Article persist pattern (Scenario-B) would
     * fail this test immediately — regression guard against orphan-row risk.
     */
    public function testEmergencyHaltExceptionPropagatesWithoutPersistingOrFallingBackToGemini(): void
    {
        $this->dispatcher->method('dispatch')->willThrowException(
            new EmergencyHaltException('flash_writer'),
        );

        // No Gemini fallback.
        $this->geminiCliService->expects($this->never())->method('execute');
        // No direct Gemini logging.
        $this->llmInvocationLogger->expects($this->never())->method('logInvocation');
        // Scenario-A codification: no persist, no flush → zero orphan rows.
        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $this->expectException(EmergencyHaltException::class);

        $primary = $this->mockSignal(200, 'Titlu halt-test', 'Rezumat');
        $verdict = new VerificationVerdict(VerdictType::FULL_FLASH, 'ok', confidence: 0.9);

        $this->writer->write($primary, [], $verdict);
    }

    /**
     * T57.P2c.4 acceptance (c + AgentRequest shape): the dispatcher receives
     * an AgentRequest carrying agentId=flash_writer, hardcoded HAIKU tier
     * (Pattern-B constant, not TierResolver-driven), the system prompt, and
     * no tierVariant (FlashWriter has no variant — single tier per
     * ADR-020 D5 Tier B).
     */
    public function testDispatchReceivesAgentRequestWithHardcodedHaikuTier(): void
    {
        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (AgentRequest $req): bool {
                $this->assertSame('flash_writer', $req->agentId);
                $this->assertSame(LlmModelTier::HAIKU, $req->tier);
                $this->assertNotNull($req->systemPrompt);
                $this->assertStringContainsString('editor al redacției Deschide', $req->systemPrompt);
                $this->assertCount(1, $req->messages);
                $this->assertSame('user', $req->messages[0]['role']);
                $this->assertNull($req->tierVariant, 'FlashWriter has no variant');

                return true;
            }))
            ->willReturn($this->happyPathAgentResponse());

        $this->categoryResolver->method('resolve')->willReturn($this->mockCategory('politica'));
        $this->aiAuthorProvider->method('getOrCreate')->willReturn($this->mockAuthor());

        $primary = $this->mockSignal(1, 'T', 'S');
        $verdict = new VerificationVerdict(VerdictType::FULL_FLASH, 'ok', confidence: 0.9);

        $this->writer->write($primary, [], $verdict);
    }

    /**
     * @param array<string, mixed>|null $metrics
     */
    private function happyPathAgentResponse(
        ?array $metrics = null,
        ?string $invocationId = '01JE0Q9ZXJQ8YHZR3S3M7E2P5H',
    ): AgentResponse {
        return $this->buildAgentResponse(
            content: $this->happyPathResponse(),
            metrics: $metrics,
            invocationId: $invocationId,
        );
    }

    /**
     * @param array<string, mixed>|null $metrics
     */
    private function buildAgentResponse(
        string $content,
        ?array $metrics = null,
        ?string $invocationId = '01JE0Q9ZXJQ8YHZR3S3M7E2P5H',
    ): AgentResponse {
        return new AgentResponse(
            content: $content,
            agentId: 'flash_writer',
            tier: LlmModelTier::HAIKU,
            model: 'claude-haiku-4-5-20251001',
            attempts: 1,
            invocationId: $invocationId,
            metrics: $metrics,
        );
    }

    private function happyPathResponse(): string
    {
        return json_encode([
            'title' => 'Un exemplu de titlu despre Chișinău',
            'lead' => 'Un lead concis cu două fraze care rezumă esența semnalului.',
            'content' => 'Corpul articolului cu diacritice corecte: ș, ț. Menționează Chișinău și evenimentul principal. Adaugă context editorial minimal, fără speculații, respectând stilul Deschide.',
            'headline_attribution' => null,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    private function mockCategory(string $slug): Category
    {
        $cat = $this->createMock(Category::class);
        $cat->method('getSlug')->willReturn($slug);

        return $cat;
    }

    private function mockAuthor(): Author
    {
        $author = $this->createMock(Author::class);
        $author->method('getSlug')->willReturn('deschide-ai');

        return $author;
    }

    private function mockSignal(int $id, string $title, string $summary): SourceSignal
    {
        $verifiedSource = $this->createMock(VerifiedSource::class);
        $verifiedSource->method('getEditorialAlignment')->willReturn(EditorialAlignment::WIRE_NEUTRAL);
        $verifiedSource->method('getName')->willReturn('Mock Source');

        $signal = $this->createMock(SourceSignal::class);
        $signal->method('getId')->willReturn($id);
        $signal->method('getTitle')->willReturn($title);
        $signal->method('getRawSummary')->willReturn($summary);
        $signal->method('getSourceUrl')->willReturn("https://example.com/{$id}");
        $signal->method('getVerifiedSource')->willReturn($verifiedSource);

        return $signal;
    }
}
