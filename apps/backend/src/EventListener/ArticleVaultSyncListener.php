<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Article;
use App\Message\Editorial\SyncArticleToVaultMessage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEntityListener(event: Events::postPersist, entity: Article::class)]
#[AsEntityListener(event: Events::postUpdate, entity: Article::class)]
#[AsEntityListener(event: Events::preRemove, entity: Article::class)]
class ArticleVaultSyncListener
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {}

    public function postPersist(Article $article): void
    {
        $this->bus->dispatch(new SyncArticleToVaultMessage(
            articleId: $article->getId(),
            action: 'sync',
        ));
    }

    public function postUpdate(Article $article): void
    {
        $this->bus->dispatch(new SyncArticleToVaultMessage(
            articleId: $article->getId(),
            action: 'sync',
        ));
    }

    /**
     * Capture data BEFORE deletion since the entity won't exist after flush.
     */
    public function preRemove(Article $article): void
    {
        $this->bus->dispatch(new SyncArticleToVaultMessage(
            articleId: $article->getId(),
            action: 'archive',
            slug: $article->getSlug(),
            createdAt: $article->getCreatedAt()?->format('c'),
        ));
    }
}
