<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\ArticleLock;
use App\Repository\ArticleLockRepository;

/**
 * @implements ProviderInterface<ArticleLock>
 */
final class ArticleLockProvider implements ProviderInterface
{
    public function __construct(
        private readonly ArticleLockRepository $lockRepository
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        // Handle single item retrieval
        if (isset($uriVariables['id'])) {
            return $this->lockRepository->find($uriVariables['id']);
        }

        // Handle collection retrieval
        return $this->lockRepository->findAll();
    }
}
