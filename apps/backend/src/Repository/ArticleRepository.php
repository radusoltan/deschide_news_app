<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Article;
use App\Entity\Topic;
use App\Enum\ArticleStatus;
use App\Enum\ArticleType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    /**
     * Find articles by tag IDs (AND logic - article must have all tags).
     *
     * @param array<int> $tagIds Array of tag IDs
     * @param string $locale Locale for translatable fields
     *
     * @return Article[]
     */
    public function findByTags(array $tagIds, string $locale = 'ro'): array
    {
        $qb = $this->createQueryBuilder('a');

        foreach ($tagIds as $index => $tagId) {
            $alias = 't' . $index;
            $qb->join('a.tags', $alias)
                ->andWhere($alias . '.id = :tagId' . $index)
                ->setParameter('tagId' . $index, $tagId);
        }

        $qb->leftJoin('a.category', 'c')
            ->addSelect('c')
            ->leftJoin('a.authors', 'au')
            ->addSelect('au')
            ->leftJoin('a.tags', 'tags')
            ->addSelect('tags')
            ->orderBy('a.publishedAt', 'DESC');

        $query = $qb->getQuery();
        $query->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        return $query->getResult();
    }

    /**
     * Find articles by tag IDs (OR logic - article has at least one tag).
     *
     * @param array<int> $tagIds Array of tag IDs
     * @param string $locale Locale for translatable fields
     *
     * @return Article[]
     */
    public function findByAnyTag(array $tagIds, string $locale = 'ro'): array
    {
        $qb = $this->createQueryBuilder('a')
            ->join('a.tags', 't')
            ->where('t.id IN (:tagIds)')
            ->setParameter('tagIds', $tagIds)
            ->leftJoin('a.category', 'c')
            ->addSelect('c')
            ->leftJoin('a.authors', 'au')
            ->addSelect('au')
            ->leftJoin('a.tags', 'tags')
            ->addSelect('tags')
            ->orderBy('a.publishedAt', 'DESC')
            ->groupBy('a.id'); // Prevent duplicates

        $query = $qb->getQuery();
        $query->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        return $query->getResult();
    }

    /**
     * Find similar articles by shared tags.
     *
     * Returns articles that share the most tags with the given article.
     *
     * @param Article $article The reference article
     * @param int $limit Maximum number of results
     * @param string $locale Locale for translatable fields
     *
     * @return Article[]
     */
    public function findSimilarByTags(Article $article, int $limit = 5, string $locale = 'ro'): array
    {
        $tagIds = $article->getTags()->map(fn ($tag) => $tag->getId())->toArray();

        if (empty($tagIds)) {
            return [];
        }

        $qb = $this->createQueryBuilder('a')
            ->join('a.tags', 't')
            ->where('t.id IN (:tagIds)')
            ->andWhere('a.id != :articleId')
            ->setParameter('tagIds', $tagIds)
            ->setParameter('articleId', $article->getId())
            ->leftJoin('a.category', 'c')
            ->addSelect('c')
            ->leftJoin('a.authors', 'au')
            ->addSelect('au')
            ->leftJoin('a.tags', 'tags')
            ->addSelect('tags')
            ->groupBy('a.id')
            ->orderBy('COUNT(t.id)', 'DESC') // Order by number of shared tags
            ->addOrderBy('a.publishedAt', 'DESC')
            ->setMaxResults($limit);

        $query = $qb->getQuery();
        $query->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        return $query->getResult();
    }

    /**
     * Count articles by tag.
     *
     * @param int $tagId The tag ID
     */
    public function countByTag(int $tagId): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->join('a.tags', 't')
            ->where('t.id = :tagId')
            ->setParameter('tagId', $tagId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find the most recent active developing-story article for a Topic (Sprint 55 T55.2, ADR-020 D9).
     *
     * An article is considered "active developing" when it:
     *  - is of type {@see ArticleType::DEVELOPING_STORY},
     *  - has at least one entry linking it to the given Topic,
     *  - is not in {@see ArticleStatus::ARCHIVED},
     *  - was updated on or after `$since` (controls the developing-story freshness window).
     *
     * Returns null when no such article exists — the caller (DevelopingStoryWriter
     * dispatcher) then falls back to FlashWriter to create a new article.
     */
    public function findDevelopingStoryForTopic(Topic $topic, \DateTimeImmutable $since): ?Article
    {
        return $this->createQueryBuilder('a')
            ->innerJoin('a.topics', 't')
            ->andWhere('t = :topic')
            ->andWhere('a.articleType = :type')
            ->andWhere('a.status != :archived')
            ->andWhere('a.updatedAt >= :since')
            ->setParameter('topic', $topic)
            ->setParameter('type', ArticleType::DEVELOPING_STORY->value)
            ->setParameter('archived', ArticleStatus::ARCHIVED->value)
            ->setParameter('since', $since)
            ->orderBy('a.updatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
