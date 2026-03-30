<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\MenuItem;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\TranslatableListener;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<MenuItem>
 */
final class MenuItemProvider implements ProviderInterface
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
        $repository = $this->entityManager->getRepository(MenuItem::class);

        $locale = $request?->headers->get('Accept-Language', 'ro') ?? 'ro';

        // Extract just the language code
        if (str_contains($locale, '-')) {
            $locale = explode('-', $locale)[0];
        }
        if (str_contains($locale, ',')) {
            $locale = explode(',', $locale)[0];
        }

        // Handle single item retrieval
        if (isset($uriVariables['id'])) {
            $queryBuilder = $repository->createQueryBuilder('mi')
                ->leftJoin('mi.category', 'c')
                ->addSelect('c')
                ->where('mi.id = :id')
                ->setParameter('id', $uriVariables['id']);

            $query = $queryBuilder->getQuery();

            $query->setHint(
                TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                $locale
            );

            return $query->getOneOrNullResult();
        }

        // Handle collection retrieval
        $queryBuilder = $repository->createQueryBuilder('mi')
            ->leftJoin('mi.category', 'c')
            ->addSelect('c');

        // Apply filters from query parameters
        if ($request) {
            if ($menu = $request->query->get('menu')) {
                $queryBuilder->andWhere('mi.menu = :menu')
                    ->setParameter('menu', $menu);
            }

            if ($request->query->has('isActive')) {
                $isActive = filter_var($request->query->get('isActive'), FILTER_VALIDATE_BOOLEAN);
                $queryBuilder->andWhere('mi.isActive = :isActive')
                    ->setParameter('isActive', $isActive);
            }
        }

        // Default ordering
        $queryBuilder->orderBy('mi.position', 'ASC');

        $query = $queryBuilder->getQuery();

        $query->setHint(
            TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        return $query->getResult();
    }
}
