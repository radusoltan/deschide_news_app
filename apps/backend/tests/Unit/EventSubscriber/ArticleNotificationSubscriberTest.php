<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\Article;
use App\Entity\Category;
use App\Enum\NotificationImportance;
use App\Enum\NotificationType;
use App\Event\ArticlePublishedEvent;
use App\Event\ArticleUpdatedEvent;
use App\EventSubscriber\ArticleNotificationSubscriber;
use App\Service\NotificationService;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ArticleNotificationSubscriberTest extends TestCase
{
    private NotificationService $notificationService;
    private ArticleNotificationSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->notificationService = $this->createMock(NotificationService::class);
        $this->subscriber = new ArticleNotificationSubscriber($this->notificationService);
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

        $this->notificationService->expects($this->once())
            ->method('notify')
            ->with(
                type: NotificationType::ARTICLE_PUBLISHED,
                title: $this->stringContains('Test Article'),
                message: $this->stringContains('Politica'),
                importance: NotificationImportance::MEDIUM,
                relatedEntityType: 'article',
                relatedEntityId: 42,
                actionUrl: '/admin/articles/42/edit',
            );

        $this->subscriber->onArticlePublished($event);
    }

    public function testOnArticlePublishedHandlesNullCategory(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getTitle')->willReturn('Orphan Article');
        $article->method('getCategory')->willReturn(null);
        $article->method('getId')->willReturn(10);

        $event = new ArticlePublishedEvent($article);

        $this->notificationService->expects($this->once())
            ->method('notify')
            ->with(
                type: NotificationType::ARTICLE_PUBLISHED,
                title: $this->stringContains('Orphan Article'),
                message: $this->stringContains('Fără categorie'),
                importance: NotificationImportance::MEDIUM,
                relatedEntityType: 'article',
                relatedEntityId: 10,
                actionUrl: '/admin/articles/10/edit',
            );

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

        $this->notificationService->expects($this->once())
            ->method('notify')
            ->with(
                type: NotificationType::ARTICLE_UPDATED,
                title: $this->stringContains('Updated Article'),
                message: $this->stringContains('Sport'),
                importance: NotificationImportance::LOW,
                relatedEntityType: 'article',
                relatedEntityId: 55,
                actionUrl: '/admin/articles/55/edit',
            );

        $this->subscriber->onArticleUpdated($event);
    }
}
