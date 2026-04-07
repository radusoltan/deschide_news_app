<?php

declare(strict_types=1);

namespace App\Service\Aggregator;

use App\Enum\PressReleaseStatus;
use App\Enum\SourceType;
use App\Repository\PressReleaseRepository;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class AggregatorStatsCollector
{
    private const STATS_CACHE_KEY = 'aggregator_source_stats';
    private const STATS_CACHE_TAG = 'aggregator_stats';
    private const CACHE_TTL = 86400; // 24 hours

    public function __construct(
        private readonly TagAwareCacheInterface $cache,
        private readonly PressReleaseRepository $pressReleaseRepository,
    ) {}

    /**
     * Collect aggregator stats from DB, cache them, and return the result.
     *
     * @return list<array{source: string, lastRun: string|null, articlesFound: int, duplicatesSkipped: int, pendingReview: int, status: string}>
     */
    public function collectAndCacheStats(): array
    {
        // Delete existing cache entry so the callback executes fresh
        $this->cache->delete(self::STATS_CACHE_KEY);

        return $this->getCachedStats();
    }

    /**
     * Get stats from cache, computing them on cache miss.
     *
     * @return list<array{source: string, lastRun: string|null, articlesFound: int, duplicatesSkipped: int, pendingReview: int, status: string}>
     */
    public function getCachedStats(): array
    {
        return $this->cache->get(self::STATS_CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(self::CACHE_TTL);
            $item->tag(self::STATS_CACHE_TAG);

            return $this->buildStats();
        });
    }

    /**
     * Invalidate the cached stats so the next request recomputes from DB.
     */
    public function invalidateCache(): void
    {
        $this->cache->invalidateTags([self::STATS_CACHE_TAG]);
    }

    /**
     * Query the database and build the stats array.
     *
     * @return list<array{source: string, lastRun: string|null, articlesFound: int, duplicatesSkipped: int, pendingReview: int, status: string}>
     */
    private function buildStats(): array
    {
        $qb = $this->pressReleaseRepository->createQueryBuilder('pr');

        // Get per-source stats: count, pending count, last receivedAt
        $rows = $qb
            ->select(
                'pr.sourceName AS source_name',
                'COUNT(pr.id) AS articles_found',
                'SUM(CASE WHEN pr.status = :pending THEN 1 ELSE 0 END) AS pending_review',
                'MAX(pr.receivedAt) AS last_run',
            )
            ->where('pr.sourceType = :sourceType')
            ->andWhere('pr.sourceName IS NOT NULL')
            ->setParameter('sourceType', SourceType::AGGREGATOR)
            ->setParameter('pending', PressReleaseStatus::PENDING)
            ->groupBy('pr.sourceName')
            ->orderBy('last_run', 'DESC')
            ->getQuery()
            ->getArrayResult();

        $now = new \DateTimeImmutable();
        $stats = [];

        foreach ($rows as $row) {
            $lastRun = $row['last_run'] instanceof \DateTimeInterface
                ? \DateTimeImmutable::createFromInterface($row['last_run'])
                : null;

            $stats[] = [
                'source' => (string) $row['source_name'],
                'lastRun' => $lastRun?->format(\DateTimeInterface::ATOM),
                'articlesFound' => (int) $row['articles_found'],
                'duplicatesSkipped' => 0,
                'pendingReview' => (int) $row['pending_review'],
                'status' => $this->determineHealthStatus($lastRun, $now),
            ];
        }

        return $stats;
    }

    /**
     * Determine health status based on how recently the source last ran.
     *
     * - 'error'   if lastRun > 6 hours ago (or null)
     * - 'warning' if lastRun > 3 hours ago
     * - 'healthy' otherwise
     */
    private function determineHealthStatus(?\DateTimeImmutable $lastRun, \DateTimeImmutable $now): string
    {
        if ($lastRun === null) {
            return 'error';
        }

        $diffSeconds = $now->getTimestamp() - $lastRun->getTimestamp();

        if ($diffSeconds > 6 * 3600) {
            return 'error';
        }

        if ($diffSeconds > 3 * 3600) {
            return 'warning';
        }

        return 'healthy';
    }
}
