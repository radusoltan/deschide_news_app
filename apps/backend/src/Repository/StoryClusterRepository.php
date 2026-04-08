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
