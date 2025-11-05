<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<Category>
 */
final class CategoryProvider implements ProviderInterface
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

        $repository = $this->entityManager->getRepository(Category::class);

        // Handle single item retrieval
        if (isset($uriVariables['id'])) {
            $queryBuilder = $repository->createQueryBuilder('c')
                ->where('c.id = :id')
                ->setParameter('id', $uriVariables['id']);

            // Apply Gedmo Translatable hints - translations loaded directly
            $query = $queryBuilder->getQuery();
            $query->setHint(
                \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                $locale
            );

            // No refresh needed - Gedmo loads translations via hints
            return $query->getOneOrNullResult();
        }

        // Handle collection retrieval
        $queryBuilder = $repository->createQueryBuilder('c');

        // Apply filters from query parameters
        if ($request) {
            // Filter by onFrontPage
            if ($request->query->has('onFrontPage')) {
                $onFrontPage = filter_var($request->query->get('onFrontPage'), FILTER_VALIDATE_BOOLEAN);
                $queryBuilder->andWhere('c.onFrontPage = :onFrontPage')
                    ->setParameter('onFrontPage', $onFrontPage);
            }

            // Filter by status
            if ($status = $request->query->get('status')) {
                $queryBuilder->andWhere('c.status = :status')
                    ->setParameter('status', $status);
            }
        }

        // Default ordering
        $queryBuilder->orderBy('c.title', 'ASC');

        $query = $queryBuilder->getQuery();

        // OPTIMIZATION: Gedmo hints load translations directly - no need to refresh!
        $query->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        // Translations are loaded by Gedmo - no refresh needed (eliminates N queries)
        return $query->getResult();
    }
}
