<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\ImportantArticlesListRepository;
use Gedmo\Translatable\TranslatableListener;
use Symfony\Component\HttpFoundation\RequestStack;

class ImportantArticlesListProvider implements ProviderInterface
{
    public function __construct(
        private readonly ImportantArticlesListRepository $repository,
        private readonly RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $locale = $this->requestStack->getCurrentRequest()?->getPreferredLanguage(['ro', 'en', 'ru']) ?? 'ro';

        if (isset($uriVariables['id'])) {
            // Single item - use query with translatable hint
            $qb = $this->repository->createQueryBuilder('ial')
                ->leftJoin('ial.article', 'a')
                ->addSelect('a')
                ->leftJoin('a.category', 'c')
                ->addSelect('c')
                ->leftJoin('a.authors', 'auth')
                ->addSelect('auth')
                ->leftJoin('a.articleImages', 'ai')
                ->addSelect('ai')
                ->leftJoin('ai.image', 'img')
                ->addSelect('img')
                ->where('ial.id = :id')
                ->setParameter('id', $uriVariables['id'])
                ->orderBy('ai.position', 'ASC');

            $query = $qb->getQuery();
            $query->setHint(TranslatableListener::HINT_TRANSLATABLE_LOCALE, $locale);

            return $query->getOneOrNullResult();
        }

        // Collection
        $qb = $this->repository->createQueryBuilder('ial')
            ->leftJoin('ial.article', 'a')
            ->addSelect('a')
            ->leftJoin('a.category', 'c')
            ->addSelect('c')
            ->leftJoin('a.authors', 'auth')
            ->addSelect('auth')
            ->leftJoin('a.articleImages', 'ai')
            ->addSelect('ai')
            ->leftJoin('ai.image', 'img')
            ->addSelect('img')
            ->andWhere('a.status != :archived_status')
            ->setParameter('archived_status', 'archived')
            ->andWhere('ARRAY_CONTAINS(a.publishedLocales, :currentLocale) = true')
            ->setParameter('currentLocale', $locale)
            ->orderBy('ial.position', 'ASC')
            ->addOrderBy('ai.position', 'ASC');

        $query = $qb->getQuery();
        $query->setHint(TranslatableListener::HINT_TRANSLATABLE_LOCALE, $locale);

        $results = $query->getResult();

        // Gedmo HINT_TRANSLATABLE_LOCALE already loads translations at query time
        // No refresh() calls needed - they cause N+1 queries

        return $results;
    }
}
