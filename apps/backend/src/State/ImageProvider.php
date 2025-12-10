<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Doctrine\Orm\Paginator;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Image;
use App\Service\ImageElasticService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<Image>
 */
final class ImageProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly ImageElasticService $imageElasticService
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

        $repository = $this->entityManager->getRepository(Image::class);

        // Handle single item retrieval
        if (isset($uriVariables['id'])) {
            $queryBuilder = $repository->createQueryBuilder('i')
                ->where('i.id = :id')
                ->setParameter('id', $uriVariables['id']);

            // Apply Gedmo Translatable hint
            $query = $queryBuilder->getQuery();
            $query->setHint(
                \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                $locale
            );

            // Gedmo HINT_TRANSLATABLE_LOCALE already loads translations at query time
            // No refresh() calls needed - they cause N+1 queries
            return $query->getOneOrNullResult();
        }

        // Handle collection retrieval
        $searchTerm = null;
        if ($request && $request->query->has('originalFilename')) {
            $searchTerm = $request->query->get('originalFilename');
        }

        // Use Elasticsearch for search if enabled and search term provided
        if ($this->imageElasticService->isEnabled() && !empty($searchTerm)) {
            // Search with Elasticsearch
            $searchResults = $this->imageElasticService->search($searchTerm);

            if (empty($searchResults)) {
                return [];
            }

            // Extract IDs from search results
            $imageIds = array_map(fn ($result) => $result['id'], $searchResults);

            // Fetch images by IDs preserving Elasticsearch order
            $queryBuilder = $repository->createQueryBuilder('i')
                ->where('i.id IN (:ids)')
                ->setParameter('ids', $imageIds);

            $query = $queryBuilder->getQuery();
            $query->setHint(
                \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                $locale
            );

            $images = $query->getResult();

            // Sort by Elasticsearch score order
            $orderedImages = [];
            $imageMap = [];
            foreach ($images as $image) {
                $imageMap[$image->getId()] = $image;
            }
            foreach ($imageIds as $id) {
                if (isset($imageMap[$id])) {
                    $orderedImages[] = $imageMap[$id];
                }
            }

            // Gedmo HINT_TRANSLATABLE_LOCALE already loads translations at query time
            // No refresh() calls needed - they cause N+1 queries
            return $orderedImages;
        }

        // Fallback to regular query (no search or Elasticsearch disabled)
        $queryBuilder = $repository->createQueryBuilder('i')
            ->orderBy('i.createdAt', 'DESC');

        // Get pagination parameters
        $page = 1;
        $itemsPerPage = 30;
        if ($request) {
            $page = max(1, (int) $request->query->get('page', 1));
            $itemsPerPage = min(100, max(1, (int) $request->query->get('itemsPerPage', 30)));
        }

        // Calculate offset for pagination
        $offset = ($page - 1) * $itemsPerPage;

        // Apply pagination directly to query builder
        $queryBuilder
            ->setFirstResult($offset)
            ->setMaxResults($itemsPerPage);

        // Create the base query with locale hint
        $query = $queryBuilder->getQuery();
        $query->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        // Use Doctrine Paginator for total count
        $doctrinePaginator = new DoctrinePaginator($query, fetchJoinCollection: false);

        // Wrap with API Platform Paginator with correct page/itemsPerPage
        return new Paginator($doctrinePaginator, $page, $itemsPerPage);
    }
}
