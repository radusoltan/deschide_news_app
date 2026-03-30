<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Article;
use App\Service\NotificationService;
use App\Service\ProcessResult;
use App\Service\TranslationResultProcessor;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(TranslationResultProcessor::class)]
#[CoversClass(ProcessResult::class)]
class TranslationResultProcessorTest extends TestCase
{
    private EntityManagerInterface $em;
    private NotificationService $notificationService;
    private TranslationResultProcessor $processor;
    private TranslationRepository $translationRepo;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->notificationService = $this->createMock(NotificationService::class);
        $this->translationRepo = $this->createMock(TranslationRepository::class);

        $this->em->method('getRepository')
            ->willReturn($this->translationRepo);

        $this->processor = new TranslationResultProcessor(
            $this->em,
            $this->notificationService,
            new NullLogger(),
        );
    }

    public function testProcessSingleLocaleRu(): void
    {
        $article = $this->createArticleStub();

        $json = json_encode([
            'translations' => [
                'ru' => [
                    'title' => 'Заголовок',
                    'lead' => 'Лид',
                    'content' => '<p>Содержание</p>',
                    'slug' => 'zagolovok',
                ],
            ],
        ]);

        $this->translationRepo->expects($this->exactly(4))
            ->method('translate');

        $this->em->expects($this->once())->method('flush');

        $result = $this->processor->process($article, $json, ['ru']);

        $this->assertSame(['ru'], $result->savedLocales);
        $this->assertFalse($result->needsReview);
    }

    public function testProcessSingleLocaleEn(): void
    {
        $article = $this->createArticleStub();

        $json = json_encode([
            'translations' => [
                'en' => [
                    'title' => 'Title',
                    'lead' => 'Lead',
                    'content' => '<p>Content</p>',
                    'slug' => 'title',
                ],
            ],
        ]);

        $this->translationRepo->expects($this->exactly(4))
            ->method('translate');

        $result = $this->processor->process($article, $json, ['en']);

        $this->assertSame(['en'], $result->savedLocales);
        $this->assertFalse($result->needsReview);
    }

    public function testProcessBothLocalesAtOnce(): void
    {
        $article = $this->createArticleStub();

        $json = json_encode([
            'translations' => [
                'ru' => [
                    'title' => 'Заголовок',
                    'lead' => 'Лид',
                    'content' => '<p>Содержание</p>',
                    'slug' => 'zagolovok',
                ],
                'en' => [
                    'title' => 'Title',
                    'lead' => 'Lead',
                    'content' => '<p>Content</p>',
                    'slug' => 'title',
                ],
            ],
        ]);

        // 4 fields x 2 locales = 8 translate calls
        $this->translationRepo->expects($this->exactly(8))
            ->method('translate');

        $result = $this->processor->process($article, $json, ['ru', 'en']);

        $this->assertSame(['ru', 'en'], $result->savedLocales);
        $this->assertFalse($result->needsReview);
    }

    public function testMissingLocaleInResponseIsSkipped(): void
    {
        $article = $this->createArticleStub();

        // Response only has 'ru', but we requested 'en' too
        $json = json_encode([
            'translations' => [
                'ru' => [
                    'title' => 'Заголовок',
                    'content' => '<p>Содержание</p>',
                ],
            ],
        ]);

        $result = $this->processor->process($article, $json, ['en']);

        $this->assertSame([], $result->savedLocales);
        $this->assertFalse($result->needsReview);
    }

    public function testMissingTranslationsKeyThrows(): void
    {
        $article = $this->createArticleStub();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('missing "translations" key');

        $this->processor->process($article, '{"foo":"bar"}', ['ru']);
    }

    public function testInvalidJsonThrows(): void
    {
        $article = $this->createArticleStub();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('invalid JSON');

        $this->processor->process($article, 'not json at all', ['ru']);
    }

    public function testQualityGateParagraphMismatchSetsNeedsReview(): void
    {
        $article = $this->createArticleStub(content: '<p>P1</p><p>P2</p><p>P3</p><p>P4</p><p>P5</p>');

        // Translation has only 2 paragraphs (60% fewer than 5 → triggers >30% threshold)
        $json = json_encode([
            'translations' => [
                'ru' => [
                    'title' => 'T',
                    'content' => '<p>P1</p><p>P2</p>',
                ],
            ],
        ]);

        $result = $this->processor->process($article, $json, ['ru']);

        $this->assertTrue($result->needsReview);
    }

    public function testQualityGateLengthMismatchSetsNeedsReview(): void
    {
        // 200 chars of content
        $longContent = '<p>' . str_repeat('a', 200) . '</p>';
        $article = $this->createArticleStub(content: $longContent);

        // Translation is way shorter (>35% difference)
        $json = json_encode([
            'translations' => [
                'ru' => [
                    'title' => 'T',
                    'content' => '<p>short</p>',
                ],
            ],
        ]);

        $result = $this->processor->process($article, $json, ['ru']);

        $this->assertTrue($result->needsReview);
    }

    public function testQualityNotesSetNeedsReview(): void
    {
        $article = $this->createArticleStub();

        $json = json_encode([
            'translations' => [
                'ru' => [
                    'title' => 'T',
                    'content' => '<p>C</p>',
                ],
            ],
            'qualityNotes' => [
                'ru' => 'Some names were hard to transliterate',
            ],
        ]);

        $result = $this->processor->process($article, $json, ['ru']);

        $this->assertTrue($result->needsReview);
    }

    public function testGeminiEnvelopeUnwrapping(): void
    {
        $article = $this->createArticleStub();

        // Gemini CLI wraps output in { session_id, response, stats } envelope
        $innerJson = json_encode([
            'translations' => [
                'ru' => [
                    'title' => 'Заголовок',
                    'content' => '<p>Содержание</p>',
                ],
            ],
        ]);

        $envelopeJson = json_encode([
            'session_id' => 'abc123',
            'response' => $innerJson,
            'stats' => ['tokens' => 100],
        ]);

        $this->translationRepo->expects($this->atLeastOnce())
            ->method('translate');

        $result = $this->processor->process($article, $envelopeJson, ['ru']);

        $this->assertSame(['ru'], $result->savedLocales);
    }

    public function testFinalizeCompletedSendsNotification(): void
    {
        $article = $this->createArticleStub();

        $article->expects($this->once())
            ->method('setTranslationStatus')
            ->with('completed');
        $article->expects($this->once())
            ->method('setTranslatedAt');
        $article->expects($this->once())
            ->method('setTranslatedBy')
            ->with('gemini-agent');

        $this->notificationService->expects($this->once())
            ->method('notify');

        $this->processor->finalize($article, 'completed', ['ru', 'en'], false);
    }

    public function testFinalizeFailedNoNotification(): void
    {
        $article = $this->createArticleStub();

        $article->expects($this->once())
            ->method('setTranslationStatus')
            ->with('failed');

        $this->notificationService->expects($this->never())
            ->method('notify');

        $this->processor->finalize($article, 'failed', [], false);
    }

    public function testFinalizeNeedsReviewSendsNotification(): void
    {
        $article = $this->createArticleStub();

        $article->expects($this->once())
            ->method('setTranslationStatus')
            ->with('needs_review');

        $this->notificationService->expects($this->once())
            ->method('notify');

        $this->processor->finalize($article, 'needs_review', ['ru'], true);
    }

    private function createArticleStub(string $content = '<p>Conținut</p>'): Article
    {
        $article = $this->createMock(Article::class);
        $article->method('getId')->willReturn(730);
        $article->method('getTitle')->willReturn('Titlu test');
        $article->method('getContent')->willReturn($content);

        return $article;
    }
}
