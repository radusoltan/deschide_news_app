<?php

declare(strict_types=1);

namespace App\Service\Analytics;

use Exception;
use Predis\Client;
use Psr\Log\LoggerInterface;

/**
 * Handles analytics operations: article views, unique visitors, trending, sessions.
 *
 * Split from App\Service\PerformanceService (T38.4 — SRP).
 */
class AnalyticsService
{
    private const STATS_NS = 'deschide_news:stats:';

    public function __construct(
        private readonly Client $redis,
        private readonly LoggerInterface $logger
    ) {
    }

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
     */
    public function getActiveSessionCount(): int
    {
        try {
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
