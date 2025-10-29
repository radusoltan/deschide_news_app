<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\ImportantArticlesList;
use App\Repository\ImportantArticlesListRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

#[AsDoctrineListener(event: Events::preRemove, priority: 500, connection: 'default')]
class ImportantArticlesListDeleteListener
{
    private const MIN_ARTICLES = 5;

    public function __construct(
        private readonly ImportantArticlesListRepository $repository
    ) {
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof ImportantArticlesList) {
            return;
        }

        $currentCount = $this->repository->count([]);

        if ($currentCount <= self::MIN_ARTICLES) {
            throw new UnprocessableEntityHttpException(
                sprintf('Cannot delete this article. The important articles list must have at least %d articles.', self::MIN_ARTICLES)
            );
        }
    }
}
