<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\ArticleImage;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProviderInterface<ArticleImage>
 */
final class ArticleImageProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $repository = $this->entityManager->getRepository(ArticleImage::class);

        // Handle single item retrieval
        if (isset($uriVariables['id'])) {
            return $repository->find($uriVariables['id']);
        }

        // Handle collection retrieval with filters
        $criteria = [];
        $orderBy = ['position' => 'ASC'];

        // Check for article.id filter in request
        if (isset($context['filters']['article.id'])) {
            $criteria['article'] = $context['filters']['article.id'];
        }

        return $repository->findBy($criteria, $orderBy);
    }
}
