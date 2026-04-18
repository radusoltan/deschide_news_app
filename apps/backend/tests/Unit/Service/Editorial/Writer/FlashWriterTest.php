<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Writer;

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
use App\Service\Ai\LlmRetryExecutor;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Editorial\PostApprovalDispatcher;
use App\Service\Editorial\Writer\AiAuthorProvider;
use App\Service\Editorial\Writer\FlashWriter;
use App\Service\Editorial\Writer\SignalCategoryResolver;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit test for {@see FlashWriter} (Sprint 55 T55.3).
 *
 * The writer orchestrates: LLM call → JSON parse → Category/Author resolution →
 * Article construction → persist → dispatch translations. Every collaborator is
 * mocked; no DB contact.
 */
class FlashWriterTest extends TestCase
{
    private LlmRetryExecutor&MockObject $llmRetryExecutor;
    private GeminiCliService&MockObject $geminiCliService;
    private SignalCategoryResolver&MockObject $categoryResolver;
    private AiAuthorProvider&MockObject $aiAuthorProvider;
    private PostApprovalDispatcher&MockObject $postApprovalDispatcher;
    private EntityManagerInterface&MockObject $em;
    private LoggerInterface&MockObject $logger;
    private FlashWriter $writer;

    protected function setUp(): void
    {
        $this->llmRetryExecutor = $this->createMock(LlmRetryExecutor::class);
        $this->geminiCliService = $this->createMock(GeminiCliService::class);
        $this->categoryResolver = $this->createMock(SignalCategoryResolver::class);
        $this->aiAuthorProvider = $this->createMock(AiAuthorProvider::class);
        $this->postApprovalDispatcher = $this->createMock(PostApprovalDispatcher::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->writer = new FlashWriter(
            $this->llmRetryExecutor,
            $this->geminiCliService,
            $this->categoryResolver,
            $this->aiAuthorProvider,
            $this->postApprovalDispatcher,
            $this->em,
            $this->logger,
        );
    }

    public function testHaikuHappyPathProducesArticleAndDispatchesTranslations(): void
    {
        $this->llmRetryExecutor->expects($this->once())
            ->method('executeWithRetry')
            ->with(
                'flash_writer',
                $this->isArray(),
                LlmModelTier::HAIKU,
                $this->isString(),
            )
            ->willReturn([
                'content' => $this->happyPathResponse(),
                'agent_id' => 'flash_writer',
                'tier' => 'haiku',
                'model' => 'claude-haiku-4-5-20251001',
                'attempts' => 1,
                'fallback_detected' => false,
                'metrics' => null,
            ]);

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

        $this->postApprovalDispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(Article::class), null, 'ro');

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
        $this->llmRetryExecutor->method('executeWithRetry')->willThrowException(
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
        $this->postApprovalDispatcher->expects($this->once())->method('dispatch');

        $this->logger->expects($this->atLeastOnce())
            ->method('warning')
            ->with('flash_writer_haiku_unavailable_trying_gemini', $this->isArray());

        $primary = $this->mockSignal(7, 'Semnal wire', 'Sumar');
        $verdict = new VerificationVerdict(VerdictType::FLASH_WITH_ATTRIBUTION, 'OK', confidence: 0.7);

        $article = $this->writer->write($primary, [], $verdict);

        $this->assertInstanceOf(Article::class, $article);
        $this->assertSame(ArticleType::FLASH, $article->getArticleType());
    }

    public function testThrowsWhenLlmResponseIsNotJson(): void
    {
        $this->llmRetryExecutor->method('executeWithRetry')->willReturn([
            'content' => 'Acesta nu este JSON, ci text liber.',
            'agent_id' => 'flash_writer',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');
        $this->postApprovalDispatcher->expects($this->never())->method('dispatch');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('could not decode');

        $primary = $this->mockSignal(1, 'T', 'S');
        $verdict = new VerificationVerdict(VerdictType::FULL_FLASH, 'ok', confidence: 0.9);

        $this->writer->write($primary, [], $verdict);
    }

    public function testStripsCodeFencesFromLlmResponse(): void
    {
        $fenced = "```json\n" . $this->happyPathResponse() . "\n```";

        $this->llmRetryExecutor->method('executeWithRetry')->willReturn([
            'content' => $fenced,
            'agent_id' => 'flash_writer',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

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
        $this->llmRetryExecutor->method('executeWithRetry')->willReturn([
            'content' => $this->happyPathResponse(),
            'agent_id' => 'flash_writer',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

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
        $this->llmRetryExecutor->method('executeWithRetry')->willReturn([
            'content' => $this->happyPathResponse(),
            'agent_id' => 'flash_writer',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

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
        $this->llmRetryExecutor->method('executeWithRetry')->willReturn([
            'content' => $this->happyPathResponse(),
            'agent_id' => 'flash_writer',
            'tier' => 'haiku',
            'model' => 'claude-haiku-4-5-20251001',
            'attempts' => 1,
            'fallback_detected' => false,
            'metrics' => null,
        ]);

        $this->categoryResolver->method('resolve')->willReturn($this->mockCategory('politica'));
        $this->aiAuthorProvider->method('getOrCreate')->willReturn($this->mockAuthor());

        $topic = $this->createMock(Topic::class);
        $topic->method('getId')->willReturn(55);

        $primary = $this->mockSignal(1, 't', 's');
        $verdict = new VerificationVerdict(VerdictType::FULL_FLASH, 'ok', confidence: 0.9);

        $article = $this->writer->write($primary, [], $verdict, $topic);

        $this->assertTrue($article->getTopics()->contains($topic));
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
