<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\LiveText;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<LiveText>
 */
final class LiveTextProvider implements ProviderInterface
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

        $repository = $this->entityManager->getRepository(LiveText::class);

        // Handle single item retrieval
        if (isset($uriVariables['id'])) {
            $queryBuilder = $repository->createQueryBuilder('lt')
                ->leftJoin('lt.author', 'u')
                ->addSelect('u')
                ->leftJoin('lt.category', 'c')
                ->addSelect('c')
                ->leftJoin('lt.collaborators', 'col')
                ->addSelect('col')
                ->leftJoin('col.user', 'colUser')
                ->addSelect('colUser')
                ->leftJoin('lt.posts', 'p')
                ->addSelect('p')
                ->leftJoin('p.author', 'postAuthor')
                ->addSelect('postAuthor')
                ->where('lt.id = :id')
                ->setParameter('id', $uriVariables['id'])
                ->orderBy('p.publishedAt', 'DESC')
                ->addOrderBy('p.position', 'ASC');

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
            }

            return $result;
        }

        // Handle collection retrieval
        // For collections, we eager load category and author to avoid N+1
        $queryBuilder = $repository->createQueryBuilder('lt')
            ->leftJoin('lt.author', 'u')
            ->addSelect('u')
            ->leftJoin('lt.category', 'c')
            ->addSelect('c')
            ->leftJoin('lt.collaborators', 'col')
            ->addSelect('col')
            ->leftJoin('col.user', 'colUser')
            ->addSelect('colUser');

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
                $queryBuilder->andWhere('lt.status = :status')
                    ->setParameter('status', $status);
            }

            // Filter by active status (LIVE or PAUSED)
            if ($request->query->has('isActive')) {
                $isActive = filter_var($request->query->get('isActive'), FILTER_VALIDATE_BOOLEAN);
                if ($isActive) {
                    $queryBuilder->andWhere('lt.status IN (:activeStatuses)')
                        ->setParameter('activeStatuses', ['live', 'paused']);
                } else {
                    $queryBuilder->andWhere('lt.status IN (:inactiveStatuses)')
                        ->setParameter('inactiveStatuses', ['draft', 'ended']);
                }
            }

            // Filter by title (partial search)
            if ($title = $request->query->get('title')) {
                $queryBuilder->andWhere('lt.title LIKE :title')
                    ->setParameter('title', '%' . $title . '%');
            }

            // Filter by slug
            if ($slug = $request->query->get('slug')) {
                $queryBuilder->andWhere('lt.slug = :slug')
                    ->setParameter('slug', $slug);
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
                        case 'startTime':
                            $queryBuilder->addOrderBy('lt.startTime', $direction);
                            break;
                        case 'endTime':
                            $queryBuilder->addOrderBy('lt.endTime', $direction);
                            break;
                        case 'createdAt':
                            $queryBuilder->addOrderBy('lt.createdAt', $direction);
                            break;
                        case 'title':
                            $queryBuilder->addOrderBy('lt.title', $direction);
                            break;
                    }
                }
            } else {
                // Default ordering: most recent first
                $queryBuilder->orderBy('lt.createdAt', 'DESC');
            }
        }

        // Apply Gedmo Translatable hint
        $query = $queryBuilder->getQuery();
        $query->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        $results = $query->getResult();

        // Refresh entities to load translatable fields (title, slug, description)
        // OPTIMIZATION: Since collaborators and category don't have computed properties
        // that load large collections, this is safe and necessary for translations
        foreach ($results as $liveText) {
            $liveText->setTranslatableLocale($locale);
            $this->entityManager->refresh($liveText);

            // Refresh category for translatable fields
            if ($liveText->getCategory()) {
                $liveText->getCategory()->setTranslatableLocale($locale);
                $this->entityManager->refresh($liveText->getCategory());
            }
        }

        // API Platform will handle pagination automatically
        return $results;
    }
}
