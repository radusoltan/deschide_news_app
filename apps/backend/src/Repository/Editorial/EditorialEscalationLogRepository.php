<?php

declare(strict_types=1);

namespace App\Repository\Editorial;

use App\Entity\Editorial\EditorialEscalationLog;
use App\Enum\Editorial\EscalationCategory;
use App\Enum\EscalationDecision;
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
     * Filter by decision status keyword (Sprint 55 T55.12 admin controller).
     *
     *   'pending'  → decision IS NULL (same as findPending but kept as an explicit status)
     *   'approved' → decision = APPROVED
     *   'rejected' → decision = REJECTED
     *   'expired'  → decision = EXPIRED
     *   anything else → empty list (caller responsibility to validate input first)
     *
     * @return list<EditorialEscalationLog>
     */
    public function findByStatus(string $status, int $limit, int $offset = 0): array
    {
        $qb = $this->createQueryBuilder('e');

        if ($status === 'pending') {
            $qb->andWhere('e.decision IS NULL')->orderBy('e.expiresAt', 'ASC');
        } else {
            $decision = EscalationDecision::tryFrom($status);
            if ($decision === null) {
                return [];
            }
            $qb->andWhere('e.decision = :decision')
                ->setParameter('decision', $decision->value)
                ->orderBy('e.decidedAt', 'DESC');
        }

        /** @var list<EditorialEscalationLog> $rows */
        $rows = $qb->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();

        return $rows;
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
        /** @var list<array{category: EscalationCategory, cnt: int|string}> $rows */
        $rows = $this->createQueryBuilder('e')
            ->select('e.category AS category', 'COUNT(e.id) AS cnt')
            ->andWhere('e.decision IS NULL')
            ->groupBy('e.category')
            ->getQuery()
            ->getResult();

        // Doctrine auto-casts the category column to the backing EscalationCategory
        // enum because the property is annotated with enumType. Use ->value to get
        // the short DB code (categ_1, family_a) as the array key — consistent with
        // what the admin controller expects when remapping to verbose enum names.
        $out = [];
        foreach ($rows as $row) {
            $out[$row['category']->value] = (int) $row['cnt'];
        }

        return $out;
    }
}
