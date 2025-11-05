<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UrlRedirect;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * URL Redirect Repository.
 *
 * Provides custom query methods for URL redirect management.
 *
 * @extends ServiceEntityRepository<UrlRedirect>
 */
class UrlRedirectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UrlRedirect::class);
    }

    /**
     * Find redirect by old URL.
     *
     * @param string $oldUrl The old URL path to search for
     *
     * @return UrlRedirect|null The redirect or null if not found
     */
    public function findByOldUrl(string $oldUrl): ?UrlRedirect
    {
        return $this->findOneBy(['oldUrl' => $oldUrl]);
    }

    /**
     * Delete unused redirects older than specified days.
     *
     * Removes redirects that have low hit counts and are old.
     * Used for cleanup maintenance.
     *
     * @param int $daysOld Minimum age in days
     * @param int $maxHitCount Maximum hit count to delete
     *
     * @return int Number of redirects deleted
     */
    public function deleteUnusedRedirects(int $daysOld, int $maxHitCount = 0): int
    {
        $date = new DateTimeImmutable("-{$daysOld} days");

        $qb = $this->createQueryBuilder('r')
            ->delete()
            ->where('r.hitCount <= :maxHits')
            ->andWhere('r.createdAt < :date')
            ->setParameter('maxHits', $maxHitCount)
            ->setParameter('date', $date);

        return $qb->getQuery()->execute();
    }

    /**
     * Delete all redirects older than specified days.
     *
     * @param int $daysOld Minimum age in days
     * @param int $minHitCountToKeep Minimum hit count to keep (optional)
     *
     * @return int Number of redirects deleted
     */
    public function deleteOldRedirects(int $daysOld, int $minHitCountToKeep = 0): int
    {
        $date = new DateTimeImmutable("-{$daysOld} days");

        $qb = $this->createQueryBuilder('r')
            ->delete()
            ->where('r.createdAt < :date')
            ->setParameter('date', $date);

        if ($minHitCountToKeep > 0) {
            $qb->andWhere('r.hitCount < :minHits')
                ->setParameter('minHits', $minHitCountToKeep);
        }

        return $qb->getQuery()->execute();
    }

    /**
     * Get redirect statistics.
     *
     * Returns comprehensive statistics about redirects in the system.
     *
     * @return array{
     *     total: int,
     *     by_type: array<string, int>,
     *     unused: int,
     *     most_used: array<int, array{id: int, oldUrl: string, newUrl: string, hitCount: int}>
     * }
     */
    public function getStatistics(): array
    {
        $em = $this->getEntityManager();

        // Total count
        $total = $this->count([]);

        // Count by type
        $byType = $em->createQuery(
            'SELECT r.type, COUNT(r.id) as count
             FROM App\Entity\UrlRedirect r
             GROUP BY r.type'
        )->getResult();

        $byTypeFormatted = [];
        foreach ($byType as $row) {
            $byTypeFormatted[$row['type']] = (int) $row['count'];
        }

        // Unused redirects (hit count = 0)
        $unused = $this->count(['hitCount' => 0]);

        // Most accessed redirects (top 10)
        $mostUsed = $em->createQuery(
            'SELECT r.id, r.oldUrl, r.newUrl, r.hitCount
             FROM App\Entity\UrlRedirect r
             ORDER BY r.hitCount DESC'
        )
            ->setMaxResults(10)
            ->getResult();

        return [
            'total' => $total,
            'by_type' => $byTypeFormatted,
            'unused' => $unused,
            'most_used' => $mostUsed,
        ];
    }

    /**
     * Find redirects by entity.
     *
     * @param string $type Entity type (article, category, author)
     * @param int $entityId Entity ID
     *
     * @return UrlRedirect[]
     */
    public function findByEntity(string $type, int $entityId): array
    {
        return $this->findBy([
            'type' => $type,
            'entityId' => $entityId,
        ]);
    }

    /**
     * Check if a redirect exists for an old URL.
     *
     * @param string $oldUrl The old URL to check
     *
     * @return bool True if redirect exists
     */
    public function redirectExists(string $oldUrl): bool
    {
        return $this->count(['oldUrl' => $oldUrl]) > 0;
    }
}
