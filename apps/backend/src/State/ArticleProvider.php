<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use ApiPlatform\State\Pagination\Pagination;
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
                ->where('a.id = :id')
                ->setParameter('id', $uriVariables['id'])
                ->orderBy('ai.position', 'ASC');

            // Apply Gedmo Translatable hint
            $query = $queryBuilder->getQuery();
            $query->setHint(
                \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                $locale
            );

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
            }

            return $result;
        }

        // Handle collection retrieval
        // For collections, we only eager load category to avoid pagination issues
        // Authors, images and thumbnails will be lazy-loaded through serialization
        $queryBuilder = $repository->createQueryBuilder('a')
            ->leftJoin('a.category', 'c')
            ->addSelect('c');

        // Apply filters from query parameters
        if ($request) {
            // Filter by category ID
            $categoryId = null;

            // Method 1: category[id]=X parsed as nested array
            $categoryArray = $request->query->all('category');
            if (is_array($categoryArray) && isset($categoryArray['id'])) {
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
            if (!empty($orderBy) && is_array($orderBy)) {
                foreach ($orderBy as $field => $direction) {
                    $direction = strtoupper((string) $direction);
                    if (in_array($direction, ['ASC', 'DESC'], true)) {
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
        $query->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        // Use Doctrine Paginator to get correct total count
        $doctrinePaginator = new DoctrinePaginator($query, fetchJoinCollection: false);
        $results = iterator_to_array($doctrinePaginator);

        foreach ($results as $result) {
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
        }

        // Return Doctrine Paginator which API Platform will wrap automatically
        return $doctrinePaginator;
    }
}
