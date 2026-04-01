<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Enum\NotificationImportance;
use App\Enum\NotificationType;
use App\Event\ArticlePublishedEvent;
use App\Event\ArticleUpdatedEvent;
use App\Service\NotificationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final readonly class ArticleNotificationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private NotificationService $notificationService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ArticlePublishedEvent::class => 'onArticlePublished',
            ArticleUpdatedEvent::class => 'onArticleUpdated',
        ];
    }

    public function onArticlePublished(ArticlePublishedEvent $event): void
    {
        $article = $event->article;
        $categoryName = $article->getCategory()?->getTitle() ?? 'Fără categorie';

        $this->notificationService->notify(
            type: NotificationType::ARTICLE_PUBLISHED,
            title: \sprintf('Articol publicat: %s', $article->getTitle()),
            message: \sprintf('Categorie: %s', $categoryName),
            importance: NotificationImportance::MEDIUM,
            relatedEntityType: 'article',
            relatedEntityId: $article->getId(),
            actionUrl: \sprintf('/admin/articles/%d/edit', $article->getId()),
        );
    }

    public function onArticleUpdated(ArticleUpdatedEvent $event): void
    {
        $article = $event->article;
        $categoryName = $article->getCategory()?->getTitle() ?? 'Fără categorie';

        $this->notificationService->notify(
            type: NotificationType::ARTICLE_UPDATED,
            title: \sprintf('Articol actualizat: %s', $article->getTitle()),
            message: \sprintf('Categorie: %s', $categoryName),
            importance: NotificationImportance::LOW,
            relatedEntityType: 'article',
            relatedEntityId: $article->getId(),
            actionUrl: \sprintf('/admin/articles/%d/edit', $article->getId()),
        );
    }
}
