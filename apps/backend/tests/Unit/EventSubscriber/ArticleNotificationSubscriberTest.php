<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\User;
use App\Enum\NotificationType;
use App\Event\ArticlePublishedEvent;
use App\Event\ArticleUpdatedEvent;
use App\EventSubscriber\ArticleNotificationSubscriber;
use App\Service\NotificationFilterService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Tests for ArticleNotificationSubscriber.
 *
 * NotificationService is final, so we build a real instance.
 * We use a mock NotificationFilterService (non-final readonly class)
 * and verify behavior through the EntityManager mock.
 */
class ArticleNotificationSubscriberTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private NotificationFilterService $filterService;
    private NotificationService $notificationService;
    private ArticleNotificationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->filterService = $this->createMock(NotificationFilterService::class);

        $this->notificationService = new NotificationService(
            $this->entityManager,
            $this->createStub(HttpClientInterface::class),
            $this->createStub(SerializerInterface::class),
            $this->filterService,
            new NullLogger(),
            'http://localhost:3000/.well-known/mercure',
            'test-jwt-token',
        );

        $this->subscriber = new ArticleNotificationSubscriber($this->notificationService);
    }

    /**
     * Create a stub User with the given id and username.
     */
    private function buildUser(int $id, string $username): User
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getUsername')->willReturn($username);

        return $user;
    }

    public function testGetSubscribedEvents(): void
    {
        $events = ArticleNotificationSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(ArticlePublishedEvent::class, $events);
        $this->assertArrayHasKey(ArticleUpdatedEvent::class, $events);
        $this->assertSame('onArticlePublished', $events[ArticlePublishedEvent::class]);
        $this->assertSame('onArticleUpdated', $events[ArticleUpdatedEvent::class]);
    }

    public function testOnArticlePublishedSendsNotification(): void
    {
        $category = $this->createStub(Category::class);
        $category->method('getTitle')->willReturn('Politica');

        $article = $this->createStub(Article::class);
        $article->method('getTitle')->willReturn('Test Article');
        $article->method('getCategory')->willReturn($category);
        $article->method('getId')->willReturn(42);

        $event = new ArticlePublishedEvent($article);

        // Filter service returns a recipient so notify() actually persists
        $this->filterService->method('getRecipients')
            ->with(NotificationType::ARTICLE_PUBLISHED)
            ->willReturn([$this->buildUser(1, 'admin')]);

        $this->entityManager->expects($this->atLeastOnce())
            ->method('persist');
        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->subscriber->onArticlePublished($event);
    }

    public function testOnArticlePublishedHandlesNullCategory(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getTitle')->willReturn('Orphan Article');
        $article->method('getCategory')->willReturn(null);
        $article->method('getId')->willReturn(10);

        $event = new ArticlePublishedEvent($article);

        $this->filterService->method('getRecipients')
            ->with(NotificationType::ARTICLE_PUBLISHED)
            ->willReturn([$this->buildUser(1, 'admin')]);

        $this->entityManager->expects($this->atLeastOnce())
            ->method('persist');
        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->subscriber->onArticlePublished($event);
    }

    public function testOnArticleUpdatedSendsNotification(): void
    {
        $category = $this->createStub(Category::class);
        $category->method('getTitle')->willReturn('Sport');

        $article = $this->createStub(Article::class);
        $article->method('getTitle')->willReturn('Updated Article');
        $article->method('getCategory')->willReturn($category);
        $article->method('getId')->willReturn(55);

        $event = new ArticleUpdatedEvent($article);

        $this->filterService->method('getRecipients')
            ->with(NotificationType::ARTICLE_UPDATED)
            ->willReturn([$this->buildUser(2, 'editor')]);

        $this->entityManager->expects($this->atLeastOnce())
            ->method('persist');
        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->subscriber->onArticleUpdated($event);
    }
}
