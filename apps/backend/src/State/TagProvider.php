<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Tag;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<Tag>
 */
final class TagProvider implements ProviderInterface
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

        // Extract just the language code (e.g., 'en' from 'en-US')
        if (str_contains($locale, '-')) {
            $locale = explode('-', $locale)[0];
        }
        if (str_contains($locale, ',')) {
            $locale = explode(',', $locale)[0];
        }

        $repository = $this->entityManager->getRepository(Tag::class);

        // Handle single item retrieval
        if (isset($uriVariables['id'])) {
            $queryBuilder = $repository->createQueryBuilder('t')
                ->where('t.id = :id')
                ->setParameter('id', $uriVariables['id']);

            // Apply Gedmo Translatable hint
            $query = $queryBuilder->getQuery();
            $query->setHint(
                \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                $locale
            );

            // Enable result cache for single tag GET (5 minutes)
            $cacheKey = \sprintf('tag_%d_%s', $uriVariables['id'], $locale);
            $query->enableResultCache(300, $cacheKey);

            $result = $query->getOneOrNullResult();

            if ($result) {
                $result->setTranslatableLocale($locale);
                // Force refresh to load translations
                $this->entityManager->refresh($result);
            }

            return $result;
        }

        // Handle collection retrieval
        $queryBuilder = $repository->createQueryBuilder('t');

        // Apply filters from query parameters
        if ($request) {
            // Search by name (partial match)
            if ($name = $request->query->get('name')) {
                $queryBuilder->andWhere('LOWER(t.name) LIKE LOWER(:name)')
                    ->setParameter('name', '%' . $name . '%');
            }

            // Search by slug (exact match)
            if ($slug = $request->query->get('slug')) {
                $queryBuilder->andWhere('t.slug = :slug')
                    ->setParameter('slug', $slug);
            }

            // Filter by minimum usage count
            if ($minUsage = $request->query->get('minUsage')) {
                $queryBuilder->andWhere('t.usageCount >= :minUsage')
                    ->setParameter('minUsage', (int) $minUsage);
            }

            // Order by
            $orderBy = $request->query->all('order');
            if (!empty($orderBy) && \is_array($orderBy)) {
                foreach ($orderBy as $field => $direction) {
                    $direction = strtoupper((string) $direction);
                    if (\in_array($direction, ['ASC', 'DESC'], true)) {
                        $queryBuilder->addOrderBy('t.' . $field, $direction);
                    }
                }
            } else {
                // Default ordering: by usage count DESC, then by name ASC
                $queryBuilder->orderBy('t.usageCount', 'DESC')
                    ->addOrderBy('t.name', 'ASC');
            }

            // Pagination
            $page = max(1, (int) $request->query->get('page', 1));
            $itemsPerPage = min(100, max(1, (int) $request->query->get('itemsPerPage', 30)));
            $offset = ($page - 1) * $itemsPerPage;

            $queryBuilder->setFirstResult($offset)
                ->setMaxResults($itemsPerPage);
        }

        // Apply Gedmo Translatable hint
        $query = $queryBuilder->getQuery();
        $query->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        // Enable result cache for tag list (5 minutes)
        $cacheKey = \sprintf('tags_list_%s_%s', $locale, md5(serialize($request?->query->all())));
        $query->enableResultCache(300, $cacheKey);

        $doctrinePaginator = new DoctrinePaginator($query, fetchJoinCollection: false);

        // Refresh each tag to load translations
        $results = iterator_to_array($doctrinePaginator);
        foreach ($results as $tag) {
            $tag->setTranslatableLocale($locale);
            $this->entityManager->refresh($tag);
        }

        return $doctrinePaginator;
    }
}
