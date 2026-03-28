<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Enum\NotificationImportance;
use App\Enum\NotificationType;
use App\Event\ArticleAutoCreatedEvent;
use App\Service\NotificationService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class ArticleAutoCreatedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [ArticleAutoCreatedEvent::class => 'onArticleAutoCreated'];
    }

    public function onArticleAutoCreated(ArticleAutoCreatedEvent $event): void
    {
        $article = $event->article;
        $title = $article->getTitle() ?? 'Articol nou';

        $this->notificationService->notify(
            type: NotificationType::ARTICLE_AUTO_CREATED,
            title: 'Agent: articol nou adăugat',
            message: \sprintf('„%s" — creat automat din email de presă și programat pentru publicare.', $title),
            importance: NotificationImportance::MEDIUM,
            relatedEntityType: 'article',
            relatedEntityId: $article->getId(),
            actionUrl: \sprintf('/admin/articles/%s/edit', $article->getId()),
        );
    }
}
