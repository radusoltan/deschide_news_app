<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PageView;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PageView>
 */
class PageViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageView::class);
    }

    /**
     * Find page views by article and date range.
     *
     * @return PageView[]
     */
    public function findByArticleAndDateRange(int $articleId, DateTime $start, DateTime $end): array
    {
        return $this->createQueryBuilder('pv')
            ->andWhere('pv.article = :articleId')
            ->andWhere('pv.viewedAt BETWEEN :start AND :end')
            ->setParameter('articleId', $articleId)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('pv.viewedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count unique visitors for an article on a specific date.
     */
    public function countUniqueVisitorsByArticle(int $articleId, DateTime $date): int
    {
        $start = (clone $date)->setTime(0, 0, 0);
        $end = (clone $date)->setTime(23, 59, 59);

        $result = $this->createQueryBuilder('pv')
            ->select('COUNT(DISTINCT pv.visitorId)')
            ->andWhere('pv.article = :articleId')
            ->andWhere('pv.viewedAt BETWEEN :start AND :end')
            ->setParameter('articleId', $articleId)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    /**
     * Get average reading time for an article on a specific date.
     */
    public function getAvgReadingTimeByArticle(int $articleId, DateTime $date): ?int
    {
        $start = (clone $date)->setTime(0, 0, 0);
        $end = (clone $date)->setTime(23, 59, 59);

        $result = $this->createQueryBuilder('pv')
            ->select('AVG(pv.sessionDuration)')
            ->andWhere('pv.article = :articleId')
            ->andWhere('pv.viewedAt BETWEEN :start AND :end')
            ->andWhere('pv.sessionDuration IS NOT NULL')
            ->setParameter('articleId', $articleId)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (int) $result : null;
    }

    /**
     * Get total views for an article on a specific date.
     */
    public function countViewsByArticleAndDate(int $articleId, DateTime $date): int
    {
        $start = (clone $date)->setTime(0, 0, 0);
        $end = (clone $date)->setTime(23, 59, 59);

        return (int) $this->createQueryBuilder('pv')
            ->select('COUNT(pv.id)')
            ->andWhere('pv.article = :articleId')
            ->andWhere('pv.viewedAt BETWEEN :start AND :end')
            ->setParameter('articleId', $articleId)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get average reading time for an article and date (alias for compatibility).
     */
    public function getAvgReadingTimeByArticleAndDate(int $articleId, DateTime $date): ?int
    {
        return $this->getAvgReadingTimeByArticle($articleId, $date);
    }

    /**
     * Get completion rate for an article on a specific date.
     * Currently returns null - will be implemented with scroll tracking.
     */
    public function getCompletionRateByArticleAndDate(int $articleId, DateTime $date): ?float
    {
        // TODO: Implement with scroll depth tracking from Redis
        return null;
    }

    /**
     * Count total views by date (all articles).
     */
    public function countViewsByDate(DateTime $date): int
    {
        $start = (clone $date)->setTime(0, 0, 0);
        $end = (clone $date)->setTime(23, 59, 59);

        return (int) $this->createQueryBuilder('pv')
            ->select('COUNT(pv.id)')
            ->andWhere('pv.viewedAt BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count new visitors by date (first-time visitors).
     */
    public function countNewVisitorsByDate(DateTime $date): int
    {
        $start = (clone $date)->setTime(0, 0, 0);
        $end = (clone $date)->setTime(23, 59, 59);

        $qb = $this->createQueryBuilder('pv');
        $qb2 = $this->createQueryBuilder('pv2');

        return (int) $qb
            ->select('COUNT(DISTINCT pv.visitorId)')
            ->andWhere('pv.viewedAt BETWEEN :start AND :end')
            ->andWhere($qb->expr()->not(
                $qb->expr()->exists(
                    $qb2->select('1')
                        ->where('pv2.visitorId = pv.visitorId')
                        ->andWhere('pv2.viewedAt < :start')
                        ->getDQL()
                )
            ))
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Count views older than a specific date.
     */
    public function countViewsOlderThan(DateTime $date): int
    {
        return (int) $this->createQueryBuilder('pv')
            ->select('COUNT(pv.id)')
            ->where('pv.viewedAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Delete views older than a specific date.
     */
    public function deleteOlderThan(DateTime $date): int
    {
        return $this->createQueryBuilder('pv')
            ->delete()
            ->where('pv.viewedAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }

    /**
     * Find views older than a specific date.
     *
     * @return PageView[]
     */
    public function findViewsOlderThan(DateTime $date): array
    {
        return $this->createQueryBuilder('pv')
            ->where('pv.viewedAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->getResult();
    }
}
