<?php

declare(strict_types=1);

namespace App\Repository\Editorial;

use App\Entity\Editorial\EditorialEscalationLog;
use App\Enum\Editorial\EscalationCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EditorialEscalationLog>
 *
 * Sprint 55 T55.8 — queries that back the admin escalation controller (T55.12),
 * the SLA expiry scheduler (T55.11), and the stats badge endpoint.
 */
class EditorialEscalationLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EditorialEscalationLog::class);
    }

    /**
     * Paginate pending (decision=null) escalations ordered by imminent expiry.
     *
     * @return list<EditorialEscalationLog>
     */
    public function findPending(int $limit, int $offset = 0): array
    {
        /** @var list<EditorialEscalationLog> $rows */
        $rows = $this->createQueryBuilder('e')
            ->andWhere('e.decision IS NULL')
            ->orderBy('e.expiresAt', 'ASC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * Paginate escalations by category (any decision state). Ordered by
     * most-recent first so admin queues show the latest action first.
     *
     * @return list<EditorialEscalationLog>
     */
    public function findByCategory(EscalationCategory $category, int $limit, int $offset = 0): array
    {
        /** @var list<EditorialEscalationLog> $rows */
        $rows = $this->createQueryBuilder('e')
            ->andWhere('e.category = :category')
            ->setParameter('category', $category->value)
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * Return all undecided rows past their SLA deadline. Drives T55.11.
     *
     * @return list<EditorialEscalationLog>
     */
    public function findSlaExpired(\DateTimeImmutable $now): array
    {
        /** @var list<EditorialEscalationLog> $rows */
        $rows = $this->createQueryBuilder('e')
            ->andWhere('e.decision IS NULL')
            ->andWhere('e.expiresAt IS NOT NULL')
            ->andWhere('e.expiresAt < :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * Total pending (decision=null) row count — drives the admin UI badge
     * without materialising all rows.
     */
    public function countPending(): int
    {
        return (int) $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->andWhere('e.decision IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Pending count broken down by category — drives the per-category badges
     * in the admin UI filter bar.
     *
     * @return array<string, int> keyed by EscalationCategory->value, only non-zero
     */
    public function countPendingByCategory(): array
    {
        /** @var list<array{category: string, cnt: int|string}> $rows */
        $rows = $this->createQueryBuilder('e')
            ->select('e.category AS category', 'COUNT(e.id) AS cnt')
            ->andWhere('e.decision IS NULL')
            ->groupBy('e.category')
            ->getQuery()
            ->getResult();

        $out = [];
        foreach ($rows as $row) {
            $out[$row['category']] = (int) $row['cnt'];
        }

        return $out;
    }
}
