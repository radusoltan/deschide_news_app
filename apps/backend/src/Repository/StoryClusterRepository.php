<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\StoryCluster;
use App\Enum\StoryClusterStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<StoryCluster>
 */
class StoryClusterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, StoryCluster::class);
    }

    /**
     * @return list<StoryCluster>
     */
    public function findTopByScore(int $limit, \DateTimeInterface $since): array
    {
        return $this->createQueryBuilder('sc')
            ->where('sc.firstSeenAt >= :since')
            ->andWhere('sc.status != :rejected')
            ->setParameter('since', $since)
            ->setParameter('rejected', StoryClusterStatus::REJECTED)
            ->orderBy('sc.importanceScore', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<StoryCluster>
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('sc')
            ->where('sc.status = :status')
            ->setParameter('status', $status)
            ->orderBy('sc.importanceScore', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find PressRelease IDs that are not yet assigned to any StoryCluster.
     *
     * @return list<int>
     */
    public function findUnclusteredPressReleaseIds(\DateTimeInterface $since): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        $subQuery = $this->getEntityManager()->createQueryBuilder()
            ->select('pr2.id')
            ->from(StoryCluster::class, 'sc2')
            ->innerJoin('sc2.pressReleases', 'pr2');

        $result = $qb->select('pr.id')
            ->from(\App\Entity\PressRelease::class, 'pr')
            ->where($qb->expr()->notIn('pr.id', $subQuery->getDQL()))
            ->andWhere('pr.createdAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('pr.createdAt', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();

        return array_map('intval', $result);
    }

    /**
     * Find active clusters (not rejected) within a time window for MLT matching.
     *
     * @return list<StoryCluster>
     */
    public function findActiveClustersInWindow(\DateTimeInterface $since): array
    {
        return $this->createQueryBuilder('sc')
            ->leftJoin('sc.pressReleases', 'pr')
            ->addSelect('pr')
            ->where('sc.firstSeenAt >= :since')
            ->andWhere('sc.status != :rejected')
            ->setParameter('since', $since)
            ->setParameter('rejected', StoryClusterStatus::REJECTED)
            ->orderBy('sc.lastUpdatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find top clusters without summaries (or all if force=true), ordered by score.
     *
     * @return list<StoryCluster>
     */
    public function findTopUnsummarized(int $limit, bool $includeExisting = false): array
    {
        $qb = $this->createQueryBuilder('sc')
            ->andWhere('sc.status != :rejected')
            ->andWhere('sc.articleCount > 1')
            ->setParameter('rejected', StoryClusterStatus::REJECTED)
            ->orderBy('sc.importanceScore', 'DESC')
            ->setMaxResults($limit);

        if (!$includeExisting) {
            $qb->andWhere('sc.summaryShort IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Full-text search on cluster headline + summary using PostgreSQL ts_rank.
     *
     * @return list<array{cluster: StoryCluster, rank: float}>
     */
    public function searchByText(string $query, int $limit = 20): array
    {
        $conn = $this->getEntityManager()->getConnection();

        // Use plainto_tsquery for safe query parsing (no special syntax needed)
        $sql = <<<'SQL'
            SELECT sc.id,
                   ts_rank_cd(
                       setweight(to_tsvector('simple', COALESCE(sc.primary_headline, '')), 'A') ||
                       setweight(to_tsvector('simple', COALESCE(sc.summary_short, '')), 'B') ||
                       setweight(to_tsvector('simple', COALESCE(sc.summary_medium, '')), 'C'),
                       plainto_tsquery('simple', :query)
                   ) AS rank
            FROM story_clusters sc
            WHERE (
                to_tsvector('simple', COALESCE(sc.primary_headline, '')) ||
                to_tsvector('simple', COALESCE(sc.summary_short, '')) ||
                to_tsvector('simple', COALESCE(sc.summary_medium, ''))
            ) @@ plainto_tsquery('simple', :query)
            AND sc.status != 'rejected'
            ORDER BY rank DESC
            LIMIT :limit
            SQL;

        $rows = $conn->fetchAllAssociative($sql, [
            'query' => $query,
            'limit' => $limit,
        ]);

        if ($rows === []) {
            return [];
        }

        $ids = array_column($rows, 'id');
        $rankById = [];
        foreach ($rows as $row) {
            $rankById[(int) $row['id']] = (float) $row['rank'];
        }

        $clusters = $this->createQueryBuilder('sc')
            ->leftJoin('sc.topics', 't')
            ->addSelect('t')
            ->where('sc.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        // Sort by rank from SQL
        $results = [];
        foreach ($clusters as $cluster) {
            $results[] = [
                'cluster' => $cluster,
                'rank' => $rankById[$cluster->getId()] ?? 0.0,
            ];
        }
        usort($results, fn(array $a, array $b) => $b['rank'] <=> $a['rank']);

        return $results;
    }

    /**
     * Find clusters that contain any of the given PressRelease IDs.
     * Ordered by importance score DESC so the best cluster is returned first.
     *
     * @param list<int> $pressReleaseIds
     * @return list<StoryCluster>
     */
    public function findClustersContainingPressReleases(array $pressReleaseIds): array
    {
        if ($pressReleaseIds === []) {
            return [];
        }

        return $this->createQueryBuilder('sc')
            ->innerJoin('sc.pressReleases', 'pr')
            ->where('pr.id IN (:prIds)')
            ->andWhere('sc.status != :rejected')
            ->setParameter('prIds', $pressReleaseIds)
            ->setParameter('rejected', StoryClusterStatus::REJECTED)
            ->orderBy('sc.importanceScore', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
