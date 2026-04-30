<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<Article>
 */
final class ArticleProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly Security $security
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
            // Validate that ID is a valid integer within PostgreSQL int4 range
            $id = $uriVariables['id'];
            if (!is_numeric($id) || $id < 1 || $id > 2147483647) {
                return null; // API Platform will return 404
            }

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

            if ($result instanceof Article) {
                // Per-locale publishing gate: ONLY applies to the public single-item
                // read (GET /api/articles/{id}) served by API Platform's ReadListener.
                //
                // Must NOT apply when API Platform's IriConverter calls this provider
                // to denormalize a write payload referencing an Article by IRI
                // (e.g. POST /api/article_images with "article": "/api/articles/{id}").
                // AbstractItemNormalizer sets $context['fetch_data'] = true in that
                // path; the public ReadListener does not.
                //
                // See ADR-027.

                // TODO(ADR-027 Open Questions #2): Replace with admin surface split
                // (separate /api/admin/articles/{id} resource + AdminArticleProvider).
                // Tracked for S+1. This bypass is a tactical hotfix — see T60.6.
                //
                // isGranted() consults the AccessDecisionManager (RoleHierarchyVoter
                // included), so a user holding ROLE_ADMIN passes the ROLE_EDITOR
                // check via the role hierarchy declared in security.yaml. Inspecting
                // $user->getRoles() directly would NOT — that returns only literal
                // assigned roles, untransformed by the hierarchy.
                $isAdminContext = $this->security->isGranted('ROLE_EDITOR');

                $isPublicRead = $operation instanceof Get
                    && $operation->getClass() === Article::class
                    && !isset($context['fetch_data'])
                    && !$isAdminContext;

                if ($isPublicRead && !$result->isPublishedInLocale($locale)) {
                    return null;
                }
                $this->populateTranslatedSlugs([$result]);
            }

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
            ->setParameter('archived_status', 'archived')
            ->andWhere('ARRAY_CONTAINS(a.publishedLocales, :currentLocale) = true')
            ->setParameter('currentLocale', $locale);

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
                $categoryParam = $request->query->get('category');
                if (\is_array($categoryParam) && isset($categoryParam['id'])) {
                    $categoryId = (int) $categoryParam['id'];
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

            // Search by title (partial match)
            if ($title = $request->query->get('title')) {
                $queryBuilder->andWhere('LOWER(a.title) LIKE LOWER(:titleSearch)')
                    ->setParameter('titleSearch', '%' . $title . '%');
            }

            // Filter by author type (e.g., ?authors.type=agency)
            $authorsArray = $request->query->all('authors');
            if (\is_array($authorsArray) && isset($authorsArray['type'])) {
                $authorType = $authorsArray['type'];
                $validAuthorTypes = ['journalist', 'agency', 'press_office'];
                if (\in_array($authorType, $validAuthorTypes, true)) {
                    $queryBuilder->andWhere('au.type = :authorType')
                        ->setParameter('authorType', $authorType);
                }
            }

            // Filter: unclassified (?unclassified=1) — articles with no article_topics rows.
            // Declared on Article via ArticleUnclassifiedFilter for OpenAPI /
            // IriTemplate discoverability; the actual predicate lives here because
            // this provider builds its own query and bypasses API Platform filter
            // chain (same pattern as category/status/isFeatured above).
            if ($request->query->has('unclassified')) {
                $raw = $request->query->get('unclassified');
                $truthy = \in_array(strtolower((string) $raw), ['1', 'true', 'yes'], true);
                if ($truthy) {
                    $queryBuilder->andWhere(
                        'NOT EXISTS (SELECT 1 FROM App\\Entity\\Article a_sub '
                        . 'JOIN a_sub.topics t_sub WHERE a_sub.id = a.id)'
                    );
                }
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

        // Enrich articles with translated slugs (article + category).
        // Iterating the paginator is safe — Doctrine's identity map ensures
        // the same entity objects are returned on subsequent iterations by API Platform.
        $articles = [];
        foreach ($doctrinePaginator as $article) {
            if ($article instanceof Article) {
                $articles[] = $article;
            }
        }
        if (!empty($articles)) {
            $this->populateTranslatedSlugs($articles);
        }

        return $doctrinePaginator;
    }

    /**
     * Batch-populate translatedSlugs on Article entities (and their categories)
     * by querying the base table (for default locale RO) and ext_translations (for EN, RU).
     *
     * @param Article[] $articles
     */
    private function populateTranslatedSlugs(array $articles): void
    {
        if (empty($articles)) {
            return;
        }

        $conn = $this->entityManager->getConnection();

        // Collect article IDs and category IDs
        $articleIds = [];
        $categoryIds = [];
        $articleMap = [];
        $categoryMap = [];

        foreach ($articles as $article) {
            $articleIds[] = (string) $article->getId();
            $articleMap[$article->getId()] = $article;

            $category = $article->getCategory();
            if ($category && $category->getId()) {
                $catId = (string) $category->getId();
                $categoryIds[$catId] = $catId;
                $categoryMap[$category->getId()] = $category;
            }
        }

        // --- Article slugs ---
        $articlePlaceholders = implode(',', array_map(fn ($id) => $conn->quote($id), $articleIds));

        // Base table slugs (= default locale RO)
        $baseSlugs = $conn->executeQuery(
            "SELECT id, slug FROM articles WHERE id IN ({$articlePlaceholders})"
        )->fetchAllAssociative();

        foreach ($baseSlugs as $row) {
            $id = (int) $row['id'];
            if (isset($articleMap[$id])) {
                $articleMap[$id]->setTranslatedSlugs(['ro' => $row['slug']]);
            }
        }

        // ext_translations slugs for EN and RU
        $translationRows = $conn->executeQuery(
            "SELECT foreign_key, locale, content FROM ext_translations "
            . "WHERE object_class = 'App\\Entity\\Article' AND field = 'slug' "
            . "AND foreign_key IN ({$articlePlaceholders})"
        )->fetchAllAssociative();

        foreach ($translationRows as $row) {
            $id = (int) $row['foreign_key'];
            if (isset($articleMap[$id])) {
                $slugs = $articleMap[$id]->getTranslatedSlugs() ?? [];
                $slugs[$row['locale']] = $row['content'];
                $articleMap[$id]->setTranslatedSlugs($slugs);
            }
        }

        // --- Category slugs ---
        if (!empty($categoryIds)) {
            $catPlaceholders = implode(',', array_map(fn ($id) => $conn->quote($id), $categoryIds));

            // Base table slugs (= default locale RO)
            $catBaseSlugs = $conn->executeQuery(
                "SELECT id, slug FROM categories WHERE id IN ({$catPlaceholders})"
            )->fetchAllAssociative();

            foreach ($catBaseSlugs as $row) {
                $id = (int) $row['id'];
                if (isset($categoryMap[$id])) {
                    $categoryMap[$id]->setTranslatedSlugs(['ro' => $row['slug']]);
                }
            }

            // ext_translations slugs for EN and RU
            $catTranslationRows = $conn->executeQuery(
                "SELECT foreign_key, locale, content FROM ext_translations "
                . "WHERE object_class = 'App\\Entity\\Category' AND field = 'slug' "
                . "AND foreign_key IN ({$catPlaceholders})"
            )->fetchAllAssociative();

            foreach ($catTranslationRows as $row) {
                $id = (int) $row['foreign_key'];
                if (isset($categoryMap[$id])) {
                    $slugs = $categoryMap[$id]->getTranslatedSlugs() ?? [];
                    $slugs[$row['locale']] = $row['content'];
                    $categoryMap[$id]->setTranslatedSlugs($slugs);
                }
            }
        }
    }
}
