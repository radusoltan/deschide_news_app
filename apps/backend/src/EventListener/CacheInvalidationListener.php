<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Article;
use App\Entity\Category;
use App\Service\Cache\CacheService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
class CacheInvalidationListener
{
    public function __construct(
        private readonly CacheService $performance
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->invalidateCache($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->invalidateCache($args->getObject());
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $this->invalidateCache($args->getObject());
    }

    private function invalidateCache(object $entity): void
    {
        if ($entity instanceof Article) {
            $this->performance->invalidateArticle($entity->getId());
        } elseif ($entity instanceof Category) {
            $this->performance->invalidateCategory($entity->getId());
        }
    }
}
