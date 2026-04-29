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

            $category = $query->getOneOrNullResult();
            if ($category instanceof Category) {
                $this->populateTranslatedSlugs([$category]);
            }

            return $category;
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

        // Order by frontPagePosition first (for front page queries), then by title
        if ($request && $request->query->has('onFrontPage')) {
            $queryBuilder->orderBy('c.frontPagePosition', 'ASC')
                ->addOrderBy('c.title', 'ASC');
        } else {
            $queryBuilder->orderBy('c.title', 'ASC');
        }

        $query = $queryBuilder->getQuery();

        // Apply Gedmo hint to load translations
        $query->setHint(
            TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        $categories = $query->getResult();
        if (\is_array($categories) && !empty($categories)) {
            $this->populateTranslatedSlugs($categories);
        }

        return $categories;
    }

    /**
     * Batch-populate translatedSlugs on Category entities by querying the base table
     * (for default locale RO) and ext_translations (for EN, RU). Mirrors the pattern
     * used in TagProvider; consumed by frontend URL builders to emit cross-locale
     * hrefs (T60.6 Cluster B).
     *
     * @param Category[] $categories
     */
    private function populateTranslatedSlugs(array $categories): void
    {
        $catMap = [];
        foreach ($categories as $category) {
            if ($category->getId() !== null) {
                $catMap[$category->getId()] = $category;
            }
        }

        if (empty($catMap)) {
            return;
        }

        $conn = $this->entityManager->getConnection();
        $placeholders = implode(',', array_map(fn ($id) => $conn->quote((string) $id), array_keys($catMap)));

        // 1. Base table slugs = RO defaults
        $baseSlugs = $conn->executeQuery(
            "SELECT id, slug FROM categories WHERE id IN ($placeholders)"
        )->fetchAllAssociative();

        foreach ($baseSlugs as $row) {
            $id = (int) $row['id'];
            if (isset($catMap[$id])) {
                $catMap[$id]->setTranslatedSlugs(['ro' => $row['slug']]);
            }
        }

        // 2. ext_translations slugs for EN and RU
        $translationRows = $conn->executeQuery(
            "SELECT foreign_key, locale, content FROM ext_translations "
            . "WHERE object_class = 'App\\Entity\\Category' AND field = 'slug' "
            . "AND foreign_key IN ($placeholders)"
        )->fetchAllAssociative();

        foreach ($translationRows as $row) {
            $id = (int) $row['foreign_key'];
            if (isset($catMap[$id])) {
                $slugs = $catMap[$id]->getTranslatedSlugs() ?? [];
                $slugs[$row['locale']] = $row['content'];
                $catMap[$id]->setTranslatedSlugs($slugs);
            }
        }
    }
}
