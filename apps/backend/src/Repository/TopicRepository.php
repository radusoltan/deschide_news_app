<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Topic;
use App\Enum\ArticleStatus;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Tree\Entity\Repository\NestedTreeRepository;

/**
 * @extends NestedTreeRepository<Topic>
 */
class TopicRepository extends NestedTreeRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, $em->getClassMetadata(Topic::class));
    }

    /**
     * Get the full topic tree, optionally filtered by locale.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getFullTree(?string $locale = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('t.root', 'ASC')
            ->addOrderBy('t.lft', 'ASC');

        $query = $qb->getQuery();

        if ($locale !== null) {
            $query->setHint(
                \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                $locale
            );
        }

        return $this->buildNestedTree($query->getResult());
    }

    /**
     * Find a topic by its slug with locale support.
     */
    public function findBySlugAndLocale(string $slug, string $locale): ?Topic
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.slug = :slug')
            ->setParameter('slug', $slug);

        $query = $qb->getQuery();
        $query->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        return $query->getOneOrNullResult();
    }

    /**
     * Find active root topics ordered by position.
     *
     * @return Topic[]
     */
    public function findActiveRoots(?string $locale = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.parent IS NULL')
            ->andWhere('t.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('t.position', 'ASC')
            ->addOrderBy('t.lft', 'ASC');

        $query = $qb->getQuery();

        if ($locale !== null) {
            $query->setHint(
                \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
                $locale
            );
        }

        return $query->getResult();
    }

    /**
     * Get article count for a topic, optionally including children.
     */
    public function getArticleCountForTopic(Topic $topic, bool $includeChildren = true): int
    {
        if ($includeChildren) {
            $qb = $this->_em->createQueryBuilder()
                ->select('COUNT(DISTINCT a.id)')
                ->from(Topic::class, 't')
                ->leftJoin('t.articles', 'a')
                ->where('t.lft >= :lft')
                ->andWhere('t.rgt <= :rgt')
                ->andWhere('t.root = :root')
                ->andWhere('a.status = :status')
                ->setParameter('lft', $topic->getLft())
                ->setParameter('rgt', $topic->getRgt())
                ->setParameter('root', $topic->getRoot() ?? $topic)
                ->setParameter('status', ArticleStatus::PUBLISHED);
        } else {
            $qb = $this->_em->createQueryBuilder()
                ->select('COUNT(DISTINCT a.id)')
                ->from(Topic::class, 't')
                ->leftJoin('t.articles', 'a')
                ->where('t.id = :topicId')
                ->andWhere('a.status = :status')
                ->setParameter('topicId', $topic->getId())
                ->setParameter('status', ArticleStatus::PUBLISHED);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Find active topics that have enough recent PressRelease coverage to
     * trigger article generation per ADR-019 D2.
     *
     * Eligibility (all required):
     *  - Topic is active (`t.is_active = true`)
     *  - At least `$minPrCount` PRs linked to the topic via PressReleaseTopic
     *    with detection timestamp >= `$windowStart`
     *  - Sum of detection confidences across those PRs >= `$minRelevance`
     *    (this is the count × avg(confidence) proxy from ADR-019 D1: avg = sum/count,
     *    so count × avg = sum)
     *  - No Article exists for this topic that was created since `$windowStart`
     *    (avoids regenerating the same article on every cron tick)
     *
     * Topics are ordered by relevance proxy DESC so the highest-signal
     * topics get processed first when callers apply a limit.
     *
     * @return Topic[]
     */
    public function findActiveWithUnprocessedPressReleasesSince(
        \DateTimeImmutable $windowStart,
        int $minPrCount,
        float $minRelevance,
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->innerJoin('t.pressReleaseTopics', 'prt')
            ->where('t.isActive = :active')
            ->andWhere('prt.detectedAt >= :windowStart')
            ->andWhere('NOT EXISTS (
                SELECT 1 FROM ' . \App\Entity\Article::class . ' a
                INNER JOIN a.topics tx
                WHERE tx.id = t.id
                AND a.createdAt >= :windowStart
            )')
            ->groupBy('t.id')
            ->having('COUNT(prt.id) >= :minPrCount')
            ->andHaving('SUM(prt.confidence) >= :minRelevance')
            ->orderBy('SUM(prt.confidence)', 'DESC')
            ->addOrderBy('t.id', 'ASC')
            ->setParameter('active', true)
            ->setParameter('windowStart', $windowStart)
            ->setParameter('minPrCount', $minPrCount)
            ->setParameter('minRelevance', $minRelevance);

        /** @var Topic[] */
        return $qb->getQuery()->getResult();
    }

    /**
     * Search topics by title.
     *
     * @return Topic[]
     */
    public function searchByTitle(string $query, string $locale = 'ro', int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('LOWER(t.title) LIKE LOWER(:query)')
            ->andWhere('t.isActive = :active')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('active', true)
            ->orderBy('t.lvl', 'ASC')
            ->addOrderBy('t.title', 'ASC')
            ->setMaxResults($limit);

        $q = $qb->getQuery();
        $q->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        return $q->getResult();
    }

    /**
     * Build a nested tree array from a flat list of Topic entities.
     *
     * @param Topic[] $topics
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildNestedTree(array $topics): array
    {
        $tree = [];
        $map = [];

        foreach ($topics as $topic) {
            $node = [
                'id' => $topic->getId(),
                'title' => $topic->getTitle(),
                'slug' => $topic->getSlug(),
                'description' => $topic->getDescription(),
                'lvl' => $topic->getLvl(),
                'position' => $topic->getPosition(),
                'isActive' => $topic->isActive(),
                'status' => $topic->getStatus()->value,
                'isSensitive' => $topic->isSensitive(),
                'isStoryLeaf' => $topic->isStoryLeaf(),
                'children' => [],
            ];

            $map[$topic->getId()] = $node;

            $parentId = $topic->getParent()?->getId();
            if ($parentId !== null && isset($map[$parentId])) {
                $map[$parentId]['children'][] = &$map[$topic->getId()];
            } else {
                $tree[] = &$map[$topic->getId()];
            }
        }

        return $tree;
    }
}
