<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Author;
use App\Enum\AuthorType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<Author>
 */
final class AuthorProvider implements ProviderInterface
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

        $repository = $this->entityManager->getRepository(Author::class);

        // Handle single item retrieval
        if (isset($uriVariables['id'])) {
            $queryBuilder = $repository->createQueryBuilder('a')
                ->where('a.id = :id')
                ->setParameter('id', $uriVariables['id']);

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
            }

            return $result;
        }

        // Handle collection retrieval
        $queryBuilder = $repository->createQueryBuilder('a')
            ->orderBy('a.lastName', 'ASC')
            ->addOrderBy('a.firstName', 'ASC');

        // Apply filters from query parameters
        if ($request) {
            // Filter by type (e.g., ?type=agency)
            if ($type = $request->query->get('type')) {
                $validTypes = array_map(fn (AuthorType $t) => $t->value, AuthorType::cases());
                if (\in_array($type, $validTypes, true)) {
                    $queryBuilder->andWhere('a.type = :type')
                        ->setParameter('type', $type);
                }
            }

            // Filter by status (e.g., ?status=active)
            if ($status = $request->query->get('status')) {
                $queryBuilder->andWhere('a.status = :status')
                    ->setParameter('status', $status);
            }

            // Filter by isActive (e.g., ?isActive=true)
            if ($request->query->has('isActive')) {
                $isActive = filter_var($request->query->get('isActive'), FILTER_VALIDATE_BOOLEAN);
                $queryBuilder->andWhere('a.isActive = :isActive')
                    ->setParameter('isActive', $isActive);
            }
        }

        $query = $queryBuilder->getQuery();
        $query->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        $results = $query->getResult();

        // Set locale on each result
        // Note: We don't refresh() here to avoid loading all lazy relationships
        // which would cause memory issues with large collections
        foreach ($results as $result) {
            $result->setTranslatableLocale($locale);
        }

        return $results;
    }
}
