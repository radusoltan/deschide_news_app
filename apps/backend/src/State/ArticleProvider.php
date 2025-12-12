<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<Article>
 */
final class ArticleProvider implements ProviderInterface
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

        $repository = $this->entityManager->getRepository(Article::class);

        // Handle single item retrieval
        if (isset($uriVariables['id'])) {
            $queryBuilder = $repository->createQueryBuilder('a')
                ->leftJoin('a.category', 'c')
                ->addSelect('c')
                ->leftJoin('a.authors', 'au')
                ->addSelect('au')
                ->leftJoin('a.articleImages', 'ai')
                ->addSelect('ai')
                ->leftJoin('ai.image', 'img')
                ->addSelect('img')
                ->leftJoin('a.tags', 't')
                ->addSelect('t')
                ->where('a.id = :id')
                ->andWhere('a.status != :archived_status')
                ->setParameter('id', $uriVariables['id'])
                ->setParameter('archived_status', 'archived')
                ->orderBy('ai.position', 'ASC');

            // Apply Gedmo Translatable hint
            $query = $queryBuilder->getQuery();
            $query->setHint(
                \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                $locale
            );
            // Set HINT_INNER_JOIN to false to allow articles without explicit translations
            // Articles in the default locale (ro) don't have entries in ext_translations table
            $query->setHint(
                \Gedmo\Translatable\TranslatableListener::HINT_INNER_JOIN,
                false
            );

            // NOTE: Result cache is handled by CachedArticleProvider decorator
            // Do NOT enable result cache here to avoid double caching
            // (CachedArticleProvider uses Redis with tag-based invalidation)

            $result = $query->getOneOrNullResult();

            // Translation loading is handled by HINT_TRANSLATABLE_LOCALE set above
            // No need to manually refresh entities

            return $result;
        }

        // Handle collection retrieval
        // OPTIMIZATION: Eager load all related entities to avoid N+1 queries
        $queryBuilder = $repository->createQueryBuilder('a')
            ->leftJoin('a.category', 'c')
            ->addSelect('c')
            ->leftJoin('a.authors', 'au')
            ->addSelect('au')
            ->leftJoin('a.tags', 't')
            ->addSelect('t')
            ->leftJoin('a.articleImages', 'ai')
            ->addSelect('ai')
            ->leftJoin('ai.image', 'img')
            ->addSelect('img')
            ->andWhere('a.status != :archived_status')
            ->setParameter('archived_status', 'archived');

        // Apply filters from query parameters
        if ($request) {
            // Filter by category ID
            $categoryId = null;

            // Method 1: categoryId=X (recommended - avoids SearchFilter conflicts)
            if ($request->query->has('categoryId')) {
                $catValue = $request->query->get('categoryId');
                if (is_numeric($catValue)) {
                    $categoryId = (int) $catValue;
                }
            }

            // Method 2: category[id]=X parsed as nested array (legacy support)
            if (!$categoryId) {
                $categoryArray = $request->query->all('category');
                if (\is_array($categoryArray) && isset($categoryArray['id'])) {
                    $categoryId = (int) $categoryArray['id'];
                }
            }

            // Method 3: Direct category=X (legacy support)
            if (!$categoryId && $request->query->has('category')) {
                $catValue = $request->query->get('category');
                if (is_numeric($catValue)) {
                    $categoryId = (int) $catValue;
                }
            }

            if ($categoryId) {
                $queryBuilder->andWhere('c.id = :categoryId')
                    ->setParameter('categoryId', $categoryId);
            }

            // Filter by status
            if ($status = $request->query->get('status')) {
                $queryBuilder->andWhere('a.status = :status')
                    ->setParameter('status', $status);
            }

            // Filter by featured
            if ($request->query->has('isFeatured')) {
                $isFeatured = filter_var($request->query->get('isFeatured'), FILTER_VALIDATE_BOOLEAN);
                $queryBuilder->andWhere('a.isFeatured = :isFeatured')
                    ->setParameter('isFeatured', $isFeatured);
            }

            // Filter by badge (breaking, alert, flash)
            if ($badge = $request->query->get('badge')) {
                // Validate badge value against enum values
                $validBadges = ['breaking', 'alert', 'flash'];
                if (\in_array($badge, $validBadges, true)) {
                    $queryBuilder->andWhere('a.badge = :badge')
                        ->setParameter('badge', $badge);
                }
            }

            // Order by
            $orderBy = $request->query->all('order');
            if (!empty($orderBy) && \is_array($orderBy)) {
                foreach ($orderBy as $field => $direction) {
                    $direction = strtoupper((string) $direction);
                    if (\in_array($direction, ['ASC', 'DESC'], true)) {
                        $queryBuilder->addOrderBy('a.' . $field, $direction);
                    }
                }
            } else {
                // Default ordering
                $queryBuilder->addOrderBy('a.publishedAt', 'DESC');
            }

            // Pagination
            $page = max(1, (int) $request->query->get('page', 1));
            $itemsPerPage = min(100, max(1, (int) $request->query->get('itemsPerPage', 20)));
            $offset = ($page - 1) * $itemsPerPage;

            $queryBuilder->setFirstResult($offset)
                ->setMaxResults($itemsPerPage);
        }

        $query = $queryBuilder->getQuery();

        // Set locale hint for Gedmo Translatable
        $query->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );
        // Set HINT_INNER_JOIN to false to allow articles without explicit translations
        // Articles in the default locale (ro) don't have entries in ext_translations table
        $query->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_INNER_JOIN,
            false
        );

        // NOTE: Result cache is handled by CachedArticleProvider decorator
        // Do NOT enable result cache here to avoid double caching
        // (CachedArticleProvider uses Redis with tag-based invalidation)

        // Use Doctrine Paginator to get correct total count
        // fetchJoinCollection=true because we're joining collections (authors, tags)
        $doctrinePaginator = new DoctrinePaginator($query, fetchJoinCollection: true);

        // NOTE: We cannot iterate the paginator here and then return it,
        // because the iterator will be exhausted. API Platform needs to iterate it itself.
        // Translation loading is handled by the HINT_TRANSLATABLE_LOCALE set above.
        // If we need to refresh entities for translations, we should use an EventSubscriber instead.

        // Return the paginator (API Platform handles the iteration)
        return $doctrinePaginator;
    }
}
