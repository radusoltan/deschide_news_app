<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\LiveTextReaction;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<LiveTextReaction>
 */
final class LiveTextReactionProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $repository = $this->entityManager->getRepository(LiveTextReaction::class);

        // Handle single item retrieval
        if (isset($uriVariables['id'])) {
            return $repository->find($uriVariables['id']);
        }

        // Handle collection retrieval
        $request = $this->requestStack->getCurrentRequest();
        $queryBuilder = $repository->createQueryBuilder('r')
            ->leftJoin('r.liveTextPost', 'p')
            ->addSelect('p')
            ->leftJoin('r.user', 'u')
            ->addSelect('u');

        // Filter by post
        if ($request && $request->query->has('liveTextPost')) {
            $postId = $request->query->get('liveTextPost');
            if (is_numeric($postId)) {
                $queryBuilder->andWhere('p.id = :postId')
                    ->setParameter('postId', (int) $postId);
            }
        }

        // Filter by reaction type
        if ($request && $request->query->has('reactionType')) {
            $reactionType = $request->query->get('reactionType');
            $queryBuilder->andWhere('r.reactionType = :reactionType')
                ->setParameter('reactionType', $reactionType);
        }

        // Order by created date
        $queryBuilder->orderBy('r.createdAt', 'DESC');

        return $queryBuilder->getQuery()->getResult();
    }
}
