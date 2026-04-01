<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Entity\Tag;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator as DoctrinePaginator;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @implements ProviderInterface<Tag>
 */
final class TagProvider implements ProviderInterface
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

        $repository = $this->entityManager->getRepository(Tag::class);

        // Handle single item retrieval
        if (isset($uriVariables['id'])) {
            $queryBuilder = $repository->createQueryBuilder('t')
                ->where('t.id = :id')
                ->setParameter('id', $uriVariables['id']);

            // Apply Gedmo Translatable hint
            $query = $queryBuilder->getQuery();
            $query->setHint(
                \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                $locale
            );

            // Enable result cache for single tag GET (5 minutes)
            $cacheKey = \sprintf('tag_%d_%s', $uriVariables['id'], $locale);
            $query->enableResultCache(300, $cacheKey);

            $result = $query->getOneOrNullResult();

            if ($result instanceof Tag) {
                $this->populateTranslatedSlugs([$result]);
            }

            return $result;
        }

        // Handle collection retrieval
        $queryBuilder = $repository->createQueryBuilder('t');

        // Apply filters from query parameters
        if ($request) {
            // Search by name (partial match)
            if ($name = $request->query->get('name')) {
                $queryBuilder->andWhere('LOWER(t.name) LIKE LOWER(:name)')
                    ->setParameter('name', '%' . $name . '%');
            }

            // Search by slug (exact match)
            if ($slug = $request->query->get('slug')) {
                $queryBuilder->andWhere('t.slug = :slug')
                    ->setParameter('slug', $slug);
            }

            // Filter by minimum usage count
            if ($minUsage = $request->query->get('minUsage')) {
                $queryBuilder->andWhere('t.usageCount >= :minUsage')
                    ->setParameter('minUsage', (int) $minUsage);
            }

            // Order by
            $orderBy = $request->query->all('order');
            if (!empty($orderBy) && \is_array($orderBy)) {
                foreach ($orderBy as $field => $direction) {
                    $direction = strtoupper((string) $direction);
                    if (\in_array($direction, ['ASC', 'DESC'], true)) {
                        $queryBuilder->addOrderBy('t.' . $field, $direction);
                    }
                }
            } else {
                // Default ordering: by usage count DESC, then by name ASC
                $queryBuilder->orderBy('t.usageCount', 'DESC')
                    ->addOrderBy('t.name', 'ASC');
            }

            // Pagination
            $page = max(1, (int) $request->query->get('page', 1));
            $itemsPerPage = min(100, max(1, (int) $request->query->get('itemsPerPage', 30)));
            $offset = ($page - 1) * $itemsPerPage;

            $queryBuilder->setFirstResult($offset)
                ->setMaxResults($itemsPerPage);
        }

        // Apply Gedmo Translatable hint
        $query = $queryBuilder->getQuery();
        $query->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        // Enable result cache for tag list (5 minutes)
        $cacheKey = \sprintf('tags_list_%s_%s', $locale, md5(serialize($request?->query->all())));
        $query->enableResultCache(300, $cacheKey);

        $doctrinePaginator = new DoctrinePaginator($query, fetchJoinCollection: false);

        // Enrich tags with translated slugs.
        // Iterating the paginator is safe here — Doctrine's identity map ensures
        // the same entity objects are returned on subsequent iterations by API Platform.
        // We only set a non-persisted property; no DB writes or refresh() calls.
        $tags = [];
        foreach ($doctrinePaginator as $tag) {
            if ($tag instanceof Tag) {
                $tags[] = $tag;
            }
        }
        if (!empty($tags)) {
            $this->populateTranslatedSlugs($tags);
        }

        return $doctrinePaginator;
    }

    /**
     * Batch-populate translatedSlugs on Tag entities by querying the base table
     * (for default locale RO) and ext_translations (for EN, RU).
     *
     * @param Tag[] $tags
     */
    private function populateTranslatedSlugs(array $tags): void
    {
        if (empty($tags)) {
            return;
        }

        $conn = $this->entityManager->getConnection();
        $tagIds = array_map(fn (Tag $t) => (string) $t->getId(), $tags);
        $tagMap = [];
        foreach ($tags as $tag) {
            $tagMap[$tag->getId()] = $tag;
        }

        $placeholders = implode(',', array_map(fn ($id) => $conn->quote($id), $tagIds));

        // 1. Get base table slugs (= default locale RO values)
        $baseSlugs = $conn->executeQuery(
            "SELECT id, slug FROM tags WHERE id IN ($placeholders)"
        )->fetchAllAssociative();

        foreach ($baseSlugs as $row) {
            $id = (int) $row['id'];
            if (isset($tagMap[$id])) {
                $tagMap[$id]->setTranslatedSlugs(['ro' => $row['slug']]);
            }
        }

        // 2. Get ext_translations slugs for EN and RU
        $translationRows = $conn->executeQuery(
            "SELECT foreign_key, locale, content FROM ext_translations "
            . "WHERE object_class = 'App\\Entity\\Tag' AND field = 'slug' "
            . "AND foreign_key IN ($placeholders)"
        )->fetchAllAssociative();

        foreach ($translationRows as $row) {
            $id = (int) $row['foreign_key'];
            if (isset($tagMap[$id])) {
                $slugs = $tagMap[$id]->getTranslatedSlugs() ?? [];
                $slugs[$row['locale']] = $row['content'];
                $tagMap[$id]->setTranslatedSlugs($slugs);
            }
        }
    }
}
