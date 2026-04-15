<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler;

use App\Dto\DuplicateCheckResult;
use App\Entity\PressRelease;
use App\Enum\NotificationImportance;
use App\Enum\NotificationType;
use App\Enum\PressReleaseStatus;
use App\Enum\SourceType;
use App\Message\Editorial\ProcessScrapedArticleMessage;
use App\MessageHandler\Editorial\ProcessScrapedArticleHandler;
use App\Repository\PressReleaseRepository;
use App\Service\CategoryDetectorService;
use App\Service\ContentDeduplicator;
use App\Service\ContentHasher;
use App\Service\NotificationFilterService;
use App\Service\NotificationService;
use App\Service\Cleaning\SourceContentCleanerRegistry;
use App\Service\ScrapedContentCleaner;
use App\Service\Scraping\RelevanceFilterService;
use App\Service\SourceAuthorResolver;
use App\Service\TopicDetectorService;
use App\Service\Translation\AggregatorTranslationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ProcessScrapedArticleHandlerTest extends TestCase
{
    private EntityManagerInterface $em;
    private ContentDeduplicator $deduplicator;
    private AggregatorTranslationService $translationService;
    private ProcessScrapedArticleHandler $handler;

    protected function setUp(): void
    {
        $contentCleaner = $this->createMock(ScrapedContentCleaner::class);
        $contentCleaner->method('clean')->willReturn('<p>Clean content</p>');
        $contentCleaner->method('extractLead')->willReturn('Clean lead text.');

        $contentHasher = $this->createMock(ContentHasher::class);
        $contentHasher->method('hash')->willReturn('abc123hash');

        $this->deduplicator = $this->createMock(ContentDeduplicator::class);

        $categoryDetector = $this->createMock(CategoryDetectorService::class);
        $categoryDetector->method('detectSlug')->willReturn('politica');

        $authorResolver = $this->createMock(SourceAuthorResolver::class);

        // Build a real NotificationService with mocked deps (it's final, can't mock it)
        $notifEm = $this->createMock(EntityManagerInterface::class);
        $httpClient = $this->createMock(HttpClientInterface::class);
        $serializer = $this->createMock(SerializerInterface::class);
        $filterService = $this->createMock(NotificationFilterService::class);
        $filterService->method('getRecipients')->willReturn([]);
        $notificationService = new NotificationService(
            $notifEm,
            $httpClient,
            $serializer,
            $filterService,
            new NullLogger(),
            'http://localhost:3000/.well-known/mercure',
            'fake-jwt-token',
        );

        $this->em = $this->createMock(EntityManagerInterface::class);

        $topicDetector = $this->createMock(TopicDetectorService::class);
        $topicDetector->method('detectTopics')->willReturn([
            ['topicId' => 1, 'confidence' => 'high', 'reason' => 'Direct mention'],
        ]);

        $relevanceFilter = new RelevanceFilterService(
            tier1Keywords: ['Moldova', 'Gov.md'],
            tier2Keywords: [],
            tier3Keywords: [],
            minScore: 1,
            logger: new NullLogger(),
        );

        $this->translationService = $this->createMock(AggregatorTranslationService::class);

        $pressReleaseRepo = $this->createMock(PressReleaseRepository::class);

        $this->handler = new ProcessScrapedArticleHandler(
            $contentCleaner,
            new SourceContentCleanerRegistry([]),
            $contentHasher,
            $this->deduplicator,
            $pressReleaseRepo,
            $categoryDetector,
            $authorResolver,
            $notificationService,
            $topicDetector,
            $relevanceFilter,
            $this->translationService,
            $this->em,
            new NullLogger(),
        );
    }

    #[Test]
    public function createsPressReleaseWhenNotDuplicate(): void
    {
        $this->deduplicator->method('isDuplicate')->willReturn(
            new DuplicateCheckResult(isDuplicate: false)
        );

        $this->em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($entity) {
                $this->assertInstanceOf(PressRelease::class, $entity);
                $this->assertSame(PressReleaseStatus::PENDING, $entity->getStatus());
                $this->assertSame(SourceType::SCRAPE, $entity->getSourceType());
                $this->assertSame('abc123hash', $entity->getContentHash());
                $this->assertSame('politica', $entity->getCategorySlug());
                $this->assertStringStartsWith('scrape:', $entity->getSourceName());
                $this->assertIsArray($entity->getSuggestedTopics());
                $this->assertNotNull($entity->getRelevanceScore());

                return true;
            }));

        $this->em->expects($this->once())->method('flush');

        $message = new ProcessScrapedArticleMessage(
            title: 'Test scraped article',
            bodyMarkdown: '<p>Raw body content</p>',
            sourceUrl: 'https://gov.md/article/123',
            sourceName: 'Gov.md',
            originalLanguage: 'ro',
            contentHash: 'original_hash',
        );

        ($this->handler)($message);
    }

    #[Test]
    public function skipsDuplicateContent(): void
    {
        $this->deduplicator->method('isDuplicate')->willReturn(
            new DuplicateCheckResult(
                isDuplicate: true,
                existingEntityType: 'PressRelease',
                existingEntityId: 5,
                existingSourceType: SourceType::EMAIL,
            )
        );

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $message = new ProcessScrapedArticleMessage(
            title: 'Duplicate article',
            bodyMarkdown: '<p>Same content</p>',
            sourceUrl: 'https://gov.md/article/456',
            sourceName: 'Gov.md',
            originalLanguage: 'ro',
            contentHash: 'dup_hash',
        );

        ($this->handler)($message);
    }

    #[Test]
    public function translatesEnglishScrapedArticleAndPreservesOriginal(): void
    {
        $this->deduplicator->method('isDuplicate')->willReturn(
            new DuplicateCheckResult(isDuplicate: false)
        );

        $this->translationService->expects($this->once())
            ->method('translateToRomanian');

        $this->em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($entity) {
                $this->assertInstanceOf(PressRelease::class, $entity);
                // Original should be preserved before translation overwrites
                $this->assertSame('EU announces new trade deal', $entity->getOriginalTitle());
                $this->assertSame('<p>Clean content</p>', $entity->getOriginalContent());
                $this->assertSame('en', $entity->getOriginalLanguage());

                return true;
            }));

        $message = new ProcessScrapedArticleMessage(
            title: 'EU announces new trade deal',
            bodyMarkdown: '<p>Raw EU content</p>',
            sourceUrl: 'https://reuters.com/article/123',
            sourceName: 'Reuters',
            originalLanguage: 'en',
            contentHash: 'en_hash',
        );

        ($this->handler)($message);
    }

    #[Test]
    public function doesNotTranslateRomanianScrapedArticle(): void
    {
        $this->deduplicator->method('isDuplicate')->willReturn(
            new DuplicateCheckResult(isDuplicate: false)
        );

        $this->translationService->expects($this->never())
            ->method('translateToRomanian');

        $this->em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($entity) {
                $this->assertInstanceOf(PressRelease::class, $entity);
                $this->assertNull($entity->getOriginalTitle());
                $this->assertNull($entity->getOriginalContent());
                $this->assertSame('ro', $entity->getOriginalLanguage());

                return true;
            }));

        $message = new ProcessScrapedArticleMessage(
            title: 'Articol moldovenesc',
            bodyMarkdown: '<p>Conținut românesc</p>',
            sourceUrl: 'https://moldpres.md/article/123',
            sourceName: 'Moldpres',
            originalLanguage: 'ro',
            contentHash: 'ro_hash',
        );

        ($this->handler)($message);
    }

    #[Test]
    public function translatesGermanScrapedArticleAndPreservesOriginal(): void
    {
        $this->deduplicator->method('isDuplicate')->willReturn(
            new DuplicateCheckResult(isDuplicate: false)
        );

        $this->translationService->expects($this->once())
            ->method('translateToRomanian');

        $this->em->expects($this->once())
            ->method('persist')
            ->with($this->callback(function ($entity) {
                $this->assertInstanceOf(PressRelease::class, $entity);
                $this->assertSame('Deutschland beschließt neues Gesetz', $entity->getOriginalTitle());
                $this->assertNotNull($entity->getOriginalContent());
                $this->assertSame('de', $entity->getOriginalLanguage());

                return true;
            }));

        $message = new ProcessScrapedArticleMessage(
            title: 'Deutschland beschließt neues Gesetz',
            bodyMarkdown: '<p>German content</p>',
            sourceUrl: 'https://example.de/article/123',
            sourceName: 'German Source',
            originalLanguage: 'de',
            contentHash: 'de_hash',
        );

        ($this->handler)($message);
    }
}
