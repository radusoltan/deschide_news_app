<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Thumbnail;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @implements ProviderInterface<Thumbnail>
 */
final class ThumbnailProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $repository = $this->entityManager->getRepository(Thumbnail::class);

        // Handle single item retrieval
        if (isset($uriVariables['id'])) {
            return $repository->find($uriVariables['id']);
        }

        // Handle collection retrieval
        return $repository->findBy([], ['createdAt' => 'DESC']);
    }
}
