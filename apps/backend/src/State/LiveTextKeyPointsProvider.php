<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\LiveTextPost;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provider for fetching key points (important posts) from a LiveText.
 *
 * @implements ProviderInterface<LiveTextPost>
 */
final class LiveTextKeyPointsProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $request = $this->requestStack->getCurrentRequest();
        $locale = $request?->headers->get('Accept-Language', 'ro') ?? 'ro';

        // Extract just the language code
        if (str_contains($locale, '-')) {
            $locale = explode('-', $locale)[0];
        }
        if (str_contains($locale, ',')) {
            $locale = explode(',', $locale)[0];
        }

        // Get liveTextId from URI variables
        $liveTextId = $uriVariables['id'] ?? null;

        if (!$liveTextId) {
            return [];
        }

        $repository = $this->entityManager->getRepository(LiveTextPost::class);

        // Query for key points only
        $queryBuilder = $repository->createQueryBuilder('p')
            ->leftJoin('p.liveText', 'lt')
            ->addSelect('lt')
            ->leftJoin('p.author', 'a')
            ->addSelect('a')
            ->where('lt.id = :liveTextId')
            ->andWhere('p.isKeyPoint = :isKeyPoint')
            ->setParameter('liveTextId', $liveTextId)
            ->setParameter('isKeyPoint', true)
            ->orderBy('p.publishedAt', 'DESC')
            ->addOrderBy('p.position', 'ASC');

        $query = $queryBuilder->getQuery();

        // Return all key points (no pagination for timeline)
        return $query->getResult();
    }
}
