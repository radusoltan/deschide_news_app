<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Article;
use Doctrine\ORM\EntityManagerInterface;
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
        $queryBuilder = $repository->createQueryBuilder('a')
            ->leftJoin('a.category', 'c')
            ->addSelect('c')
            ->leftJoin('a.authors', 'au')
            ->addSelect('au')
            ->leftJoin('a.articleImages', 'ai')
            ->addSelect('ai')
            ->leftJoin('ai.image', 'img')
            ->addSelect('img')
            ->orderBy('a.publishedAt', 'DESC')
            ->addOrderBy('ai.position', 'ASC');

        $query = $queryBuilder->getQuery();
        $query->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        $results = $query->getResult();

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

        return $results;
    }
}
