<?php

declare(strict_types=1);

namespace App\Service;

use Exception;
use Predis\Client;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Psr\Log\LoggerInterface;

class MetricsService
{
    private const NAMESPACE = 'deschide_news';

    public function __construct(
        private readonly CollectorRegistry $registry,
        private readonly Client $redis,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Record cache hit.
     */
    public function recordCacheHit(string $layer): void
    {
        try {
            $counter = $this->registry->getOrRegisterCounter(
                self::NAMESPACE,
                'cache_hits_total',
                'Total number of cache hits',
                ['layer']
            );
            $counter->inc(['layer' => $layer]);
        } catch (Exception $e) {
            $this->logger->error('Failed to record cache hit', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Record cache miss.
     */
    public function recordCacheMiss(string $layer): void
    {
        try {
            $counter = $this->registry->getOrRegisterCounter(
                self::NAMESPACE,
                'cache_misses_total',
                'Total number of cache misses',
                ['layer']
            );
            $counter->inc(['layer' => $layer]);
        } catch (Exception $e) {
            $this->logger->error('Failed to record cache miss', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Record HTTP request duration.
     */
    public function recordHttpRequestDuration(string $path, float $duration, bool $cached): void
    {
        try {
            $histogram = $this->registry->getOrRegisterHistogram(
                self::NAMESPACE,
                'http_request_duration_seconds',
                'HTTP request duration in seconds',
                ['path', 'cached'],
                [0.01, 0.05, 0.1, 0.2, 0.5, 1.0, 2.0, 5.0]
            );
            $histogram->observe($duration, [$path, $cached ? 'true' : 'false']);
        } catch (Exception $e) {
            $this->logger->error('Failed to record HTTP request duration', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Record page view.
     */
    public function recordPageView(): void
    {
        try {
            $counter = $this->registry->getOrRegisterCounter(
                self::NAMESPACE,
                'pageviews_total',
                'Total number of page views'
            );
            $counter->inc();
        } catch (Exception $e) {
            $this->logger->error('Failed to record page view', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Set cache memory usage gauge.
     */
    public function setCacheMemoryUsage(string $namespace, int $bytes): void
    {
        try {
            $gauge = $this->registry->getOrRegisterGauge(
                self::NAMESPACE,
                'cache_memory_bytes',
                'Cache memory usage in bytes',
                ['namespace']
            );
            $gauge->set($bytes, ['namespace' => $namespace]);
        } catch (Exception $e) {
            $this->logger->error('Failed to set cache memory usage', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Set active sessions gauge.
     */
    public function setActiveSessionsCount(int $count): void
    {
        try {
            $gauge = $this->registry->getOrRegisterGauge(
                self::NAMESPACE,
                'active_sessions_current',
                'Current number of active sessions'
            );
            $gauge->set($count, []);
        } catch (Exception $e) {
            $this->logger->error('Failed to set active sessions count', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Set unique visitors gauge.
     */
    public function setUniqueVisitorsCount(string $date, int $count): void
    {
        try {
            $gauge = $this->registry->getOrRegisterGauge(
                self::NAMESPACE,
                'unique_visitors_total',
                'Total unique visitors count',
                ['date']
            );
            $gauge->set($count, ['date' => $date]);
        } catch (Exception $e) {
            $this->logger->error('Failed to set unique visitors count', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Set article views gauge.
     */
    public function setArticleViews(int $articleId, int $views): void
    {
        try {
            $gauge = $this->registry->getOrRegisterGauge(
                self::NAMESPACE,
                'article_views_total',
                'Total views per article',
                ['article_id']
            );
            $gauge->set($views, ['article_id' => (string) $articleId]);
        } catch (Exception $e) {
            $this->logger->error('Failed to set article views', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Calculate and set cache hit rate.
     */
    public function updateCacheHitRate(string $layer): void
    {
        try {
            // Get Redis memory info for cache namespace
            $info = $this->redis->info('memory');
            if (isset($info['Memory']['used_memory'])) {
                $this->setCacheMemoryUsage('cache', (int) $info['Memory']['used_memory']);
            }

            $gauge = $this->registry->getOrRegisterGauge(
                self::NAMESPACE,
                'cache_hit_rate',
                'Cache hit rate by layer',
                ['layer']
            );

            // Note: Actual hit rate is calculated by Prometheus from counters
            // This is just a placeholder for compatibility
            $gauge->set(0, ['layer' => $layer]);
        } catch (Exception $e) {
            $this->logger->error('Failed to update cache hit rate', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Render metrics in Prometheus format.
     */
    public function renderMetrics(): string
    {
        try {
            $renderer = new RenderTextFormat();

            return $renderer->render($this->registry->getMetricFamilySamples());
        } catch (Exception $e) {
            $this->logger->error('Failed to render metrics', ['error' => $e->getMessage()]);

            return '';
        }
    }

    /**
     * Get active session count from Redis.
     */
    public function getActiveSessionCount(): int
    {
        try {
            // Count active session keys in Redis
            $keys = $this->redis->keys('deschide_news:stats:session:active:*');

            return \count($keys);
        } catch (Exception $e) {
            $this->logger->error('Failed to get active session count', ['error' => $e->getMessage()]);

            return 0;
        }
    }
}
