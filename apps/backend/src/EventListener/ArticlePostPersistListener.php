<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Article;
use App\Message\Editorial\IngestArticleMessage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEntityListener(event: Events::postPersist, entity: Article::class)]
class ArticlePostPersistListener
{
    public function __construct(
        private readonly MessageBusInterface $bus,
    ) {}

    public function postPersist(Article $article): void
    {
        // Dispatch AI ingestion (entity extraction, MOC update, connections)
        // Handler skips if article.ingestedAt is already set
        $this->bus->dispatch(new IngestArticleMessage(
            articleId: $article->getId(),
        ));
    }
}
