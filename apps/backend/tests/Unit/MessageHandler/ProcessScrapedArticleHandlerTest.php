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
use App\Service\CategoryDetectorService;
use App\Service\ContentDeduplicator;
use App\Service\ContentHasher;
use App\Service\NotificationFilterService;
use App\Service\NotificationService;
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

        $translationService = $this->createMock(AggregatorTranslationService::class);

        $this->handler = new ProcessScrapedArticleHandler(
            $contentCleaner,
            $contentHasher,
            $this->deduplicator,
            $categoryDetector,
            $authorResolver,
            $notificationService,
            $topicDetector,
            $relevanceFilter,
            $translationService,
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
}
