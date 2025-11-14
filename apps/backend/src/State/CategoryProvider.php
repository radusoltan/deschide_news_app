<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Category;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\TranslatableListener;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<Category>
 */
final class CategoryProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
        private readonly TranslatableListener $translatableListener
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $request = $this->requestStack->getCurrentRequest();
        $repository = $this->entityManager->getRepository(Category::class);

        // Get the current locale from request (LocaleSubscriber sets it globally)
        $locale = $request?->getLocale() ?? 'ro';

        // Handle single item retrieval
        if (isset($uriVariables['id'])) {
            $queryBuilder = $repository->createQueryBuilder('c')
                ->where('c.id = :id')
                ->setParameter('id', $uriVariables['id']);

            $query = $queryBuilder->getQuery();

            // Apply Gedmo hint to load translations
            $query->setHint(
                TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                $locale
            );

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

        // Apply Gedmo hint to load translations
        $query->setHint(
            TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        return $query->getResult();
    }
}
