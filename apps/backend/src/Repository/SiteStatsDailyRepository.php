<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SiteStatsDaily;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SiteStatsDaily>
 */
class SiteStatsDailyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SiteStatsDaily::class);
    }

    /**
     * Find stats by date range.
     *
     * @return SiteStatsDaily[]
     */
    public function findByDateRange(DateTime $start, DateTime $end): array
    {
        return $this->createQueryBuilder('ssd')
            ->andWhere('ssd.date BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('ssd.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find stats for a specific date.
     */
    public function findOneByDate(DateTime $date): ?SiteStatsDaily
    {
        return $this->createQueryBuilder('ssd')
            ->andWhere('ssd.date = :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get total visits across all time.
     */
    public function getTotalVisits(): int
    {
        $result = $this->createQueryBuilder('ssd')
            ->select('SUM(ssd.totalVisits)')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Get total unique visitors across all time.
     */
    public function getTotalUniqueVisitors(): int
    {
        $result = $this->createQueryBuilder('ssd')
            ->select('SUM(ssd.uniqueVisitors)')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Get average bounce rate for date range.
     */
    public function getAvgBounceRate(DateTime $start, DateTime $end): ?float
    {
        $result = $this->createQueryBuilder('ssd')
            ->select('AVG(ssd.bounceRate)')
            ->andWhere('ssd.date BETWEEN :start AND :end')
            ->andWhere('ssd.bounceRate IS NOT NULL')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (float) $result : null;
    }
}
