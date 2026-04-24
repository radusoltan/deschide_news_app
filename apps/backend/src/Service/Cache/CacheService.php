<?php

declare(strict_types=1);

namespace App\Service\Cache;

use Exception;
use Predis\Client;
use Psr\Log\LoggerInterface;

/**
 * Handles cache operations: get, set, delete, pattern-based invalidation.
 *
 * Split from App\Service\PerformanceService (T38.4 — SRP).
 */
class CacheService
{
    private const CACHE_NS = 'deschide_news:cache:';

    public function __construct(
        private readonly Client $redis,
        private readonly LoggerInterface $logger
    ) {
    }

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
        $this->deleteCachedPattern("api:articles:{$articleId}:*");
        $this->deleteCachedPattern('api:articles:list:*');
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
}
