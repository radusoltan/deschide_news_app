<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provider for archived articles only.
 *
 * @implements ProviderInterface<Article>
 */
final class ArchivedArticleProvider implements ProviderInterface
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
                ->andWhere('a.status = :archived_status')
                ->setParameter('id', $uriVariables['id'])
                ->setParameter('archived_status', 'archived')
                ->orderBy('ai.position', 'ASC');

            $query = $queryBuilder->getQuery();
            $query->setHint(
                \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                $locale
            );

            // Enable result cache for single archived article (2 hours - archives change rarely)
            $cacheKey = \sprintf('archived_article_%d_%s', $uriVariables['id'], $locale);
            $query->enableResultCache(7200, $cacheKey);

            $result = $query->getOneOrNullResult();

            if ($result) {
                $result->setTranslatableLocale($locale);
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

                foreach ($result->getTags() as $tag) {
                    $tag->setTranslatableLocale($locale);
                    $this->entityManager->refresh($tag);
                }
            }

            return $result;
        }

        // Handle collection retrieval - only archived articles
        $queryBuilder = $repository->createQueryBuilder('a')
            ->leftJoin('a.category', 'c')
            ->addSelect('c')
            ->leftJoin('a.authors', 'au')
            ->addSelect('au')
            ->leftJoin('a.tags', 't')
            ->addSelect('t')
            ->andWhere('a.status = :archived_status')
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

            // Filter by archive reason
            if ($archiveReason = $request->query->get('archiveReason')) {
                $queryBuilder->andWhere('a.archiveReason = :archiveReason')
                    ->setParameter('archiveReason', $archiveReason);
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
                // Default ordering for archives: most recently archived first
                $queryBuilder->addOrderBy('a.archivedAt', 'DESC');
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

        // Enable result cache for archived collection (1 hour - archives change rarely)
        $cacheKey = \sprintf(
            'archived_articles_list_%s_p%d_ipp%d_%s_%s',
            $locale,
            $page ?? 1,
            $itemsPerPage ?? 20,
            md5($request?->query->get('category', '')),
            $request?->query->get('archiveReason', 'all')
        );
        $query->enableResultCache(3600, $cacheKey);

        // Use Doctrine Paginator to get correct total count
        $doctrinePaginator = new DoctrinePaginator($query, fetchJoinCollection: true);

        // Refresh entities to load translations
        $results = iterator_to_array($doctrinePaginator);
        foreach ($results as $article) {
            $article->setTranslatableLocale($locale);
            $this->entityManager->refresh($article);

            if ($article->getCategory()) {
                $article->getCategory()->setTranslatableLocale($locale);
                $this->entityManager->refresh($article->getCategory());
            }

            foreach ($article->getAuthors() as $author) {
                $author->setTranslatableLocale($locale);
                $this->entityManager->refresh($author);
            }

            foreach ($article->getTags() as $tag) {
                $tag->setTranslatableLocale($locale);
                $this->entityManager->refresh($tag);
            }
        }

        // Return the paginator (API Platform handles the iteration)
        return $doctrinePaginator;
    }
}
