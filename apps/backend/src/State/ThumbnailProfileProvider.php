<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\ThumbnailProfile;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<ThumbnailProfile>
 */
final class ThumbnailProfileProvider implements ProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $repository = $this->entityManager->getRepository(ThumbnailProfile::class);

        // Extract locale from Accept-Language header
        $request = $this->requestStack->getCurrentRequest();
        $locale = $request?->headers->get('Accept-Language', 'ro') ?? 'ro';

        // Extract just the language code (e.g., "en-US" -> "en")
        if (str_contains($locale, '-')) {
            $locale = explode('-', $locale)[0];
        }

        // Handle single item retrieval
        if (isset($uriVariables['id'])) {
            $query = $repository->createQueryBuilder('p')
                ->where('p.id = :id')
                ->setParameter('id', $uriVariables['id'])
                ->getQuery();

            $query->setHint(\Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE, $locale);

            $result = $query->getOneOrNullResult();

            if ($result) {
                $result->setTranslatableLocale($locale);
                $this->entityManager->refresh($result);
            }

            return $result;
        }

        // Handle collection retrieval
        $query = $repository->createQueryBuilder('p')
            ->orderBy('p.category', 'ASC')
            ->addOrderBy('p.width', 'ASC')
            ->getQuery();

        $query->setHint(\Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE, $locale);

        $results = $query->getResult();

        foreach ($results as $result) {
            $result->setTranslatableLocale($locale);
            $this->entityManager->refresh($result);
        }

        return $results;
    }
}
