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

            // Enable result cache for single article GET (1 hour)
            $cacheKey = \sprintf('article_%d_%s', $uriVariables['id'], $locale);
            $query->enableResultCache(3600, $cacheKey);

            $result = $query->getOneOrNullResult();

            if ($result) {
                $result->setTranslatableLocale($locale);
                // Force refresh to load translations
                $this->entityManager->refresh($result);

                // Set locale for related entities
                if ($result->getCategory()) {
                    $result->getCategory()->setTranslatableLocale($locale);
                    $this->entityManager->refresh($result->getCategory());
                }

                foreach ($result->getAuthors() as $author) {
                    $author->setTranslatableLocale($locale);
                    $this->entityManager->refresh($author);
                }

                // Refresh tags for translatable fields (name, slug, description)
                foreach ($result->getTags() as $tag) {
                    $tag->setTranslatableLocale($locale);
                    $this->entityManager->refresh($tag);
                }
            }

            return $result;
        }

        // Handle collection retrieval
        // OPTIMIZATION: Eager load category, authors, and tags to avoid N+1 queries
        // Images/thumbnails are loaded separately if needed by serialization groups
        $queryBuilder = $repository->createQueryBuilder('a')
            ->leftJoin('a.category', 'c')
            ->addSelect('c')
            ->leftJoin('a.authors', 'au')
            ->addSelect('au')
            ->leftJoin('a.tags', 't')
            ->addSelect('t')
            ->andWhere('a.status != :archived_status')
            ->setParameter('archived_status', 'archived');

        // Apply filters from query parameters
        if ($request) {
            // Filter by category ID
            $categoryId = null;

            // Method 1: category[id]=X parsed as nested array
            $categoryArray = $request->query->all('category');
            if (\is_array($categoryArray) && isset($categoryArray['id'])) {
                $categoryId = (int) $categoryArray['id'];
            }

            // Method 2: Direct category=X
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

        // Enable result cache for collection (30 minutes)
        // Cache key includes page, itemsPerPage, filters, and locale
        $cacheKey = \sprintf(
            'articles_list_%s_p%d_ipp%d_%s_%s_%s',
            $locale,
            $page ?? 1,
            $itemsPerPage ?? 20,
            md5($request?->query->get('category', '')),
            $request?->query->get('status', 'all'),
            $request?->query->get('isFeatured', 'all')
        );
        $query->enableResultCache(1800, $cacheKey);  // 30 minutes

        // Use Doctrine Paginator to get correct total count
        // fetchJoinCollection=true because we're joining collections (authors)
        $doctrinePaginator = new DoctrinePaginator($query, fetchJoinCollection: true);

        // OPTIMIZATION: Refresh entities to load translations, but avoid N+1 by using eager-loaded data
        // Since we removed articleCount from serialization groups, this won't cause collection loading
        $results = iterator_to_array($doctrinePaginator);
        foreach ($results as $article) {
            // Refresh article for translatable fields (title, slug, lead)
            $article->setTranslatableLocale($locale);
            $this->entityManager->refresh($article);

            // Refresh category for translatable fields (title, slug) - SAFE because no articleCount
            if ($article->getCategory()) {
                $article->getCategory()->setTranslatableLocale($locale);
                $this->entityManager->refresh($article->getCategory());
            }

            // Refresh authors for translatable fields (bio) - SAFE because no articleCount
            foreach ($article->getAuthors() as $author) {
                $author->setTranslatableLocale($locale);
                $this->entityManager->refresh($author);
            }

            // Refresh tags for translatable fields (name, slug, description)
            foreach ($article->getTags() as $tag) {
                $tag->setTranslatableLocale($locale);
                $this->entityManager->refresh($tag);
            }
        }

        // Return the paginator (API Platform handles the iteration)
        return $doctrinePaginator;
    }
}
