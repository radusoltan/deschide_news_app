<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Repository\ImportantArticlesListRepository;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\TranslatableListener;
use Symfony\Component\HttpFoundation\RequestStack;

class ImportantArticlesListProvider implements ProviderInterface
{
    public function __construct(
        private readonly ImportantArticlesListRepository $repository,
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        $locale = $this->requestStack->getCurrentRequest()?->getPreferredLanguage(['ro', 'en', 'ru']) ?? 'ro';

        if (isset($uriVariables['id'])) {
            // Single item
            $item = $this->repository->find($uriVariables['id']);
            if ($item && $item->getArticle()) {
                $this->loadArticleTranslation($item->getArticle(), $locale);
            }

            return $item;
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
            ->orderBy('ial.position', 'ASC')
            ->addOrderBy('ai.position', 'ASC');

        $query = $qb->getQuery();
        $query->setHint(TranslatableListener::HINT_TRANSLATABLE_LOCALE, $locale);

        $results = $query->getResult();

        // Load translations for each article and related entities
        foreach ($results as $item) {
            if ($item && $item->getArticle()) {
                $this->loadArticleTranslation($item->getArticle(), $locale);
            }
        }

        return $results;
    }

    private function loadArticleTranslation($article, string $locale): void
    {
        if (!$article) {
            return;
        }

        $this->entityManager->refresh($article);
        $article->setTranslatableLocale($locale);
        $this->entityManager->refresh($article);

        if ($article->getCategory()) {
            $category = $article->getCategory();
            $this->entityManager->refresh($category);
            $category->setTranslatableLocale($locale);
            $this->entityManager->refresh($category);
        }
    }
}
