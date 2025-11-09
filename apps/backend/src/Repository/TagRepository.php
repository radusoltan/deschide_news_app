<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Tag;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    /**
     * Find most popular tags by usage count.
     *
     * @return Tag[]
     */
    public function findPopularTags(int $limit = 20, string $locale = 'ro'): array
    {
        $qb = $this->createQueryBuilder('t')
            ->orderBy('t.usageCount', 'DESC')
            ->setMaxResults($limit);

        $query = $qb->getQuery();
        $query->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        return $query->getResult();
    }

    /**
     * Search tags by name (for auto-complete).
     *
     * @return Tag[]
     */
    public function findByNameSearch(string $query, string $locale = 'ro', int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('LOWER(t.name) LIKE LOWER(:query)')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('t.usageCount', 'DESC')
            ->setMaxResults($limit);

        $query = $qb->getQuery();
        $query->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        return $query->getResult();
    }

    /**
     * Find or create tag by name.
     */
    public function findOrCreateByName(string $name, string $locale = 'ro'): Tag
    {
        // Try to find existing tag
        $qb = $this->createQueryBuilder('t')
            ->where('LOWER(t.name) = LOWER(:name)')
            ->setParameter('name', $name)
            ->setMaxResults(1);

        $query = $qb->getQuery();
        $query->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        $tag = $query->getOneOrNullResult();

        if (!$tag) {
            // Create new tag
            $tag = new Tag();
            $tag->setName($name);
            $tag->setTranslatableLocale($locale);

            $this->getEntityManager()->persist($tag);
        }

        return $tag;
    }

    /**
     * Find tags with no articles (for cleanup).
     *
     * @return Tag[]
     */
    public function findUnusedTags(DateTimeImmutable $olderThan): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.usageCount = 0')
            ->andWhere('t.createdAt < :date')
            ->setParameter('date', $olderThan);

        return $qb->getQuery()->getResult();
    }
}
