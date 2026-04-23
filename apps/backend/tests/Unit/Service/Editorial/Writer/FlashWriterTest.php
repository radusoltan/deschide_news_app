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
use App\Service\Editorial\Llm\LlmPromptAssembler;
use App\Service\Editorial\Writer\AiAuthorProvider;
use App\Service\Editorial\Writer\FlashWriter;
use App\Service\Editorial\Writer\SignalCategoryResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit test for {@see FlashWriter} (Sprint 55 T55.3; T57.P2c.4 AgentDispatcher
 * migration; T57.P8 ADR-024 D3 downgrade-only policy retirement).
 *
 * The writer orchestrates: LLM call → JSON parse → Category/Author resolution →
 * Article construction → persist → dispatch translations. Every collaborator is
 * mocked; no DB contact. Post-P8 there is no cross-provider fallback — on LLM
 * retry exhaust the writer emits `editorial_review_queue` and rethrows.
 */
class FlashWriterTest extends TestCase
{
    private AgentDispatcher&MockObject $dispatcher;
    private SignalCategoryResolver&MockObject $categoryResolver;
    private AiAuthorProvider&MockObject $aiAuthorProvider;
    private EntityManagerInterface&MockObject $em;
    private LoggerInterface&MockObject $logger;
    private FlashWriter $writer;

    protected function setUp(): void
    {
        $this->dispatcher = $this->createMock(AgentDispatcher::class);
        $this->categoryResolver = $this->createMock(SignalCategoryResolver::class);
        $this->aiAuthorProvider = $this->createMock(AiAuthorProvider::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->writer = new FlashWriter(
            $this->dispatcher,
            $this->categoryResolver,
            $this->aiAuthorProvider,
            $this->em,
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

    /**
     * T57.P8 (ADR-024 D3) — downgrade-only policy retired. On retry exhaust
     * the writer emits `editorial_review_queue` and rethrows; no Gemini
     * fallback, no article persisted.
     */
    public function testLlmUnavailablePropagatesWithEditorialReviewLog(): void
    {
        $topic = $this->createMock(Topic::class);
        $topic->method('getId')->willReturn(77);

        $this->dispatcher->method('dispatch')->willThrowException(
            new LlmUnavailableException(
                agentId: 'flash_writer',
                tier: LlmModelTier::HAIKU,
                attempts: 4,
            ),
        );

        // No article persisted — exhaust fires before buildArticle/persist.
        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        // Uniform D2 payload assertion.
        $captured = null;
        $this->logger->expects($this->atLeastOnce())
            ->method('warning')
            ->willReturnCallback(function (string $channel, array $payload) use (&$captured): void {
                if ($channel === 'editorial_review_queue') {
                    $captured = $payload;
                }
            });

        $this->expectException(LlmUnavailableException::class);

        $primary = $this->mockSignal(42, 'Titlu', 'Sumar');
        $verdict = new VerificationVerdict(VerdictType::FULL_FLASH, 'ok', confidence: 0.9);

        try {
            $this->writer->write($primary, [], $verdict, $topic);
        } finally {
            $this->assertIsArray($captured, 'editorial_review_queue log line must be emitted');
            $this->assertSame('flash_writer', $captured['agent_id']);
            $this->assertSame('article', $captured['entity_type']);
            $this->assertNull($captured['entity_id'], 'pre-creation — no article id yet');
            $this->assertSame(['signal_id' => 42, 'topic_id' => 77], $captured['entity_refs']);
            $this->assertNull($captured['invocation_id'], 'exhaust path yields null invocation_id in P8');
            $this->assertSame('haiku', $captured['tier_attempted']);
            $this->assertSame('haiku_exhausted', $captured['reason']);
        }
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
     * leave orphan Article rows in the DB. Scenario-A persistence safety:
     * persist() and flush() must NEVER be invoked when halt propagates from
     * the dispatcher.
     */
    public function testEmergencyHaltExceptionPropagatesWithoutPersisting(): void
    {
        $this->dispatcher->method('dispatch')->willThrowException(
            new EmergencyHaltException('flash_writer'),
        );

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
