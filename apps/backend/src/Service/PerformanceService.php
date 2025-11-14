<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Predis\Client;
use Psr\Log\LoggerInterface;

/**
 * Unified service for caching and statistics.
 * Combines cache operations and real-time tracking.
 */
class PerformanceService
{
    private const CACHE_NS = 'deschide_news:cache:';

    private const STATS_NS = 'deschide_news:stats:';

    // Cache TTL constants
    private const TTL_ARTICLE = 3600;        // 1 hour

    private const TTL_ARTICLE_LIST = 300;    // 5 minutes

    private const TTL_CATEGORY = 3600;       // 1 hour

    private const TTL_TRENDING = 300;        // 5 minutes

    public function __construct(
        private readonly Client $redis,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger
    ) {
    }

    // ====================================================================
    // CACHE METHODS
    // ====================================================================

    /**
     * Get cached value.
     */
    public function getCached(string $key): mixed
    {
        try {
            $value = $this->redis->get(self::CACHE_NS . $key);

            if ($value === null) {
                return null;
            }

            return unserialize($value);
        } catch (Exception $e) {
            $this->logger->error('Cache get failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Set cached value with TTL.
     */
    public function setCached(string $key, mixed $value, int $ttl): bool
    {
        try {
            $result = $this->redis->setex(
                self::CACHE_NS . $key,
                $ttl,
                serialize($value)
            );

            return $result === 'OK' || $result === true;
        } catch (Exception $e) {
            $this->logger->error('Cache set failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Delete cached value.
     */
    public function deleteCached(string $key): bool
    {
        try {
            return $this->redis->del([self::CACHE_NS . $key]) > 0;
        } catch (Exception $e) {
            $this->logger->error('Cache delete failed', [
                'key' => $key,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Delete multiple keys by pattern.
     */
    public function deleteCachedPattern(string $pattern): int
    {
        try {
            $keys = $this->redis->keys(self::CACHE_NS . $pattern);
            if (empty($keys)) {
                return 0;
            }

            return $this->redis->del($keys);
        } catch (Exception $e) {
            $this->logger->error('Cache pattern delete failed', [
                'pattern' => $pattern,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Invalidate article cache (all locales).
     */
    public function invalidateArticle(int $articleId): void
    {
        // Delete article cache in all locales
        $this->deleteCachedPattern("api:articles:{$articleId}:*");

        // Also invalidate article lists
        $this->deleteCachedPattern('api:articles:list:*');

        // Invalidate trending
        $this->deleteCached('api:trending');

        $this->logger->info('Article cache invalidated', ['article_id' => $articleId]);
    }

    /**
     * Invalidate category cache.
     */
    public function invalidateCategory(int $categoryId): void
    {
        $this->deleteCachedPattern("api:categories:{$categoryId}:*");
        $this->deleteCachedPattern('api:categories:list:*');
        $this->deleteCachedPattern('api:articles:list:*');

        $this->logger->info('Category cache invalidated', ['category_id' => $categoryId]);
    }

    // ====================================================================
    // STATISTICS METHODS
    // ====================================================================

    /**
     * Increment article view counter.
     */
    public function incrementArticleViews(int $articleId): void
    {
        try {
            $key = self::STATS_NS . "article:views:{$articleId}";
            $this->redis->incr($key);

            // Also add to trending (sorted set)
            $trendingKey = self::STATS_NS . 'trending:24h';
            $this->redis->zincrby($trendingKey, 1, "article:{$articleId}");
            $this->redis->expire($trendingKey, 86400); // 24 hours
        } catch (Exception $e) {
            $this->logger->error('Failed to increment article views', [
                'article_id' => $articleId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Track unique visitor for article.
     * Returns true if new visitor, false if already counted.
     */
    public function trackUniqueVisitor(int $articleId, string $visitorId): bool
    {
        try {
            $date = date('Y-m-d');
            $key = self::STATS_NS . "article:visitors:{$articleId}:{$date}";

            // Returns 1 if new, 0 if already exists
            $isNew = $this->redis->sadd($key, [$visitorId]);
            $this->redis->expire($key, 2592000); // 30 days

            return $isNew === 1;
        } catch (Exception $e) {
            $this->logger->error('Failed to track unique visitor', [
                'article_id' => $articleId,
                'visitor_id' => $visitorId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Track site-wide unique visitor using HyperLogLog.
     */
    public function trackSiteVisitor(string $visitorId): void
    {
        try {
            $date = date('Y-m-d');
            $key = self::STATS_NS . "site:visitors:{$date}";
            $this->redis->pfadd($key, [$visitorId]);
            $this->redis->expire($key, 2592000); // 30 days
        } catch (Exception $e) {
            $this->logger->error('Failed to track site visitor', [
                'visitor_id' => $visitorId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get article view count from Redis.
     */
    public function getArticleViews(int $articleId): int
    {
        try {
            $key = self::STATS_NS . "article:views:{$articleId}";

            return (int) ($this->redis->get($key) ?: 0);
        } catch (Exception $e) {
            $this->logger->error('Failed to get article views', [
                'article_id' => $articleId,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get unique visitor count using HyperLogLog.
     */
    public function getUniqueVisitorCount(string $date): int
    {
        try {
            $key = self::STATS_NS . "site:visitors:{$date}";

            return $this->redis->pfcount([$key]);
        } catch (Exception $e) {
            $this->logger->error('Failed to get unique visitor count', [
                'date' => $date,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get trending articles (top N).
     */
    public function getTrendingArticles(int $limit = 10): array
    {
        try {
            $key = self::STATS_NS . 'trending:24h';
            $results = $this->redis->zrevrange($key, 0, $limit - 1, ['WITHSCORES' => true]);

            $trending = [];
            foreach ($results as $member => $score) {
                if (preg_match('/article:(\d+)/', $member, $matches)) {
                    $trending[] = [
                        'article_id' => (int) $matches[1],
                        'views' => (int) $score,
                    ];
                }
            }

            return $trending;
        } catch (Exception $e) {
            $this->logger->error('Failed to get trending articles', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Get count of active sessions from Redis.
     * Sessions are tracked with temporary keys.
     */
    public function getActiveSessionCount(): int
    {
        try {
            // Count active session keys
            // This is a placeholder - you'd need to track sessions properly
            // For now, return 0 or implement based on your session tracking strategy
            $keys = $this->redis->keys(self::STATS_NS . 'session:active:*');

            return \is_array($keys) ? \count($keys) : 0;
        } catch (Exception $e) {
            $this->logger->error('Failed to get active session count', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }
}
