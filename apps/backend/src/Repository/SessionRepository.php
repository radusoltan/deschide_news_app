<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Session;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Session>
 */
class SessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Session::class);
    }

    /**
     * Find active sessions by visitor ID.
     *
     * @return Session[]
     */
    public function findActiveSessionsByVisitor(string $visitorId): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.visitorId = :visitorId')
            ->andWhere('s.endedAt IS NULL')
            ->setParameter('visitorId', $visitorId)
            ->orderBy('s.startedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calculate bounce rate for a specific date.
     * Bounce rate = sessions with pageCount = 1 / total sessions.
     */
    public function calculateBounceRate(DateTime $date): float
    {
        $start = (clone $date)->setTime(0, 0, 0);
        $end = (clone $date)->setTime(23, 59, 59);

        // Total sessions
        $totalSessions = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.startedAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        if ($totalSessions === 0 || $totalSessions === null) {
            return 0.0;
        }

        // Bounced sessions (pageCount = 1)
        $bouncedSessions = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.startedAt BETWEEN :start AND :end')
            ->andWhere('s.pageCount = 1')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return ($bouncedSessions / $totalSessions) * 100;
    }

    /**
     * Calculate average session duration for a specific date (in seconds).
     */
    public function calculateAvgDuration(DateTime $date): int
    {
        $start = (clone $date)->setTime(0, 0, 0);
        $end = (clone $date)->setTime(23, 59, 59);

        $result = $this->createQueryBuilder('s')
            ->select('AVG(s.duration)')
            ->andWhere('s.startedAt BETWEEN :start AND :end')
            ->andWhere('s.duration IS NOT NULL')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Find sessions by date range.
     *
     * @return Session[]
     */
    public function findByDateRange(DateTime $start, DateTime $end): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.startedAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('s.startedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total sessions for a specific date.
     */
    public function countSessionsByDate(DateTime $date): int
    {
        $start = (clone $date)->setTime(0, 0, 0);
        $end = (clone $date)->setTime(23, 59, 59);

        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.startedAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
