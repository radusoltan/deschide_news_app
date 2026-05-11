<?php

declare(strict_types=1);

namespace App\State\Article;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Article;
use App\Message\CheckOrphanedTagsMessage;
use App\Service\Article\ArticleCacheInvalidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handles DELETE operations for Article entities.
 *
 * @implements ProcessorInterface<Article>
 */
final class ArticleDeleteProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
        private readonly ArticleCacheInvalidator $cacheInvalidator,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        if (!$data instanceof Article) {
            return null;
        }

        $articleId = $data->getId();

        $tagIds = [];
        foreach ($data->getTags() as $tag) {
            $tagIds[] = $tag->getId();
            $tag->setUsageCount(max(0, $tag->getUsageCount() - 1));
        }

        $this->entityManager->getConnection()->executeStatement(
            'DELETE FROM ext_translations WHERE object_class = ? AND foreign_key = ?',
            [Article::class, (string) $data->getId()]
        );

        $this->entityManager->remove($data);
        $this->entityManager->flush();

        if ($articleId) {
            $this->cacheInvalidator->invalidate($articleId);
        }

        if (!empty($tagIds)) {
            $this->messageBus->dispatch(new CheckOrphanedTagsMessage($tagIds));
        }

        return null;
    }
}
