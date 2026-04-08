<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\PressRelease;
use App\Service\Clustering\PressReleaseIndexer;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * Automatically indexes PressReleases in Elasticsearch when they are persisted.
 * This ensures the clustering pipeline always has fresh data to match against.
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
class PressReleaseIndexListener
{
    public function __construct(
        private readonly PressReleaseIndexer $indexer,
    ) {}

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->indexIfPressRelease($args);
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->indexIfPressRelease($args);
    }

    private function indexIfPressRelease(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        if (!$entity instanceof PressRelease) {
            return;
        }

        $this->indexer->index($entity);
    }
}
