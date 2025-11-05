<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\LiveTextPost;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<LiveTextPost>
 */
final class LiveTextPostProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $request = $this->requestStack->getCurrentRequest();
        $repository = $this->entityManager->getRepository(LiveTextPost::class);

        // Handle single item retrieval
        if (isset($uriVariables['id'])) {
            $queryBuilder = $repository->createQueryBuilder('p')
                ->leftJoin('p.liveText', 'lt')
                ->addSelect('lt')
                ->leftJoin('p.author', 'u')
                ->addSelect('u')
                ->where('p.id = :id')
                ->setParameter('id', $uriVariables['id']);

            return $queryBuilder->getQuery()->getOneOrNullResult();
        }

        // Handle collection retrieval
        // Eager load liveText and author to prevent N+1 queries
        $queryBuilder = $repository->createQueryBuilder('p')
            ->leftJoin('p.liveText', 'lt')
            ->addSelect('lt')
            ->leftJoin('p.author', 'u')
            ->addSelect('u');

        // Apply filters from query parameters
        if ($request) {
            // Filter by liveText ID
            $liveTextId = null;

            // Method 1: liveText[id]=X parsed as nested array
            $liveTextArray = $request->query->all('liveText');
            if (\is_array($liveTextArray) && isset($liveTextArray['id'])) {
                $liveTextId = (int) $liveTextArray['id'];
            }

            // Method 2: Direct liveText=X
            if (!$liveTextId && $request->query->has('liveText')) {
                $ltValue = $request->query->get('liveText');
                if (is_numeric($ltValue)) {
                    $liveTextId = (int) $ltValue;
                }
            }

            if ($liveTextId) {
                $queryBuilder->andWhere('lt.id = :liveTextId')
                    ->setParameter('liveTextId', $liveTextId);
            }

            // Filter by author ID
            $authorId = null;

            // Method 1: author[id]=X parsed as nested array
            $authorArray = $request->query->all('author');
            if (\is_array($authorArray) && isset($authorArray['id'])) {
                $authorId = (int) $authorArray['id'];
            }

            // Method 2: Direct author=X
            if (!$authorId && $request->query->has('author')) {
                $authValue = $request->query->get('author');
                if (is_numeric($authValue)) {
                    $authorId = (int) $authValue;
                }
            }

            if ($authorId) {
                $queryBuilder->andWhere('u.id = :authorId')
                    ->setParameter('authorId', $authorId);
            }

            // Filter by isKeyPoint
            if ($request->query->has('isKeyPoint')) {
                $isKeyPoint = filter_var($request->query->get('isKeyPoint'), FILTER_VALIDATE_BOOLEAN);
                $queryBuilder->andWhere('p.isKeyPoint = :isKeyPoint')
                    ->setParameter('isKeyPoint', $isKeyPoint);
            }

            // Filter by content (partial search)
            if ($content = $request->query->get('content')) {
                $queryBuilder->andWhere('p.content LIKE :content')
                    ->setParameter('content', '%' . $content . '%');
            }

            // Apply ordering
            $orderParam = $request->query->all('order');
            if (\is_array($orderParam)) {
                foreach ($orderParam as $field => $direction) {
                    $direction = strtoupper($direction);
                    if (!\in_array($direction, ['ASC', 'DESC'], true)) {
                        $direction = 'DESC';
                    }

                    switch ($field) {
                        case 'publishedAt':
                            $queryBuilder->addOrderBy('p.publishedAt', $direction);
                            break;
                        case 'position':
                            $queryBuilder->addOrderBy('p.position', $direction);
                            break;
                        case 'createdAt':
                            $queryBuilder->addOrderBy('p.createdAt', $direction);
                            break;
                    }
                }
            } else {
                // Default ordering: most recent first
                $queryBuilder->orderBy('p.publishedAt', 'DESC');
            }
        }

        // API Platform will handle pagination automatically
        return $queryBuilder->getQuery()->getResult();
    }
}
