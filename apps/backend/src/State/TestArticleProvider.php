<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\TestArticle;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<TestArticle>
 */
final class TestArticleProvider implements ProviderInterface
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

        $repository = $this->entityManager->getRepository(TestArticle::class);

        // Handle single item retrieval
        if (isset($uriVariables['id'])) {
            $queryBuilder = $repository->createQueryBuilder('ta')
                ->where('ta.id = :id')
                ->setParameter('id', $uriVariables['id']);

            // Apply Gedmo Translatable hint with fallback mode
            $query = $queryBuilder->getQuery();
            $query->setHint(
                \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                $locale
            );
            // Don't use HINT_INNER_JOIN to allow fallback to default locale

            $result = $query->getOneOrNullResult();

            if ($result) {
                $result->setTranslatableLocale($locale);
                // Force refresh to load translations
                $this->entityManager->refresh($result);
            }

            return $result;
        }

        // Handle collection retrieval
        $queryBuilder = $repository->createQueryBuilder('ta');

        $query = $queryBuilder->getQuery();
        $query->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );
        // Don't use HINT_INNER_JOIN to allow fallback to default locale

        $results = $query->getResult();

        foreach ($results as $result) {
            $result->setTranslatableLocale($locale);
            $this->entityManager->refresh($result);
        }

        return $results;
    }
}
