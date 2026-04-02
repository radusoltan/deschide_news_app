<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\MetricsService;
use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Psr\Log\LoggerInterface;

class MetricsServiceTest extends TestCase
{
    private CollectorRegistry $registry;
    private Client $redis;
    private LoggerInterface $logger;
    private MetricsService $service;

    protected function setUp(): void
    {
        $this->registry = $this->createMock(CollectorRegistry::class);
        $this->redis = $this->createMock(Client::class);
        $this->logger = $this->createStub(LoggerInterface::class);

        $this->service = new MetricsService(
            $this->registry,
            $this->redis,
            $this->logger,
        );
    }

    // ====================================================================
    // recordCacheHit() Tests
    // ====================================================================

    #[Test]
    public function recordCacheHitRegistersCounterAndIncrements(): void
    {
        $counter = $this->createMock(Counter::class);
        $counter->expects($this->once())
            ->method('inc')
            ->with(['layer' => 'L1']);

        $this->registry->expects($this->once())
            ->method('getOrRegisterCounter')
            ->with('deschide_news', 'cache_hits_total', 'Total number of cache hits', ['layer'])
            ->willReturn($counter);

        $this->service->recordCacheHit('L1');
    }

    #[Test]
    public function recordCacheHitHandlesException(): void
    {
        $this->registry->expects($this->once())
            ->method('getOrRegisterCounter')
            ->willThrowException(new Exception('Registry error'));

        // Should not throw
        $this->service->recordCacheHit('L1');

        $this->assertTrue(true);
    }

    #[Test]
    public function recordCacheHitWithDifferentLayers(): void
    {
        $counter = $this->createMock(Counter::class);
        $counter->expects($this->once())
            ->method('inc')
            ->with(['layer' => 'L2']);

        $this->registry->method('getOrRegisterCounter')->willReturn($counter);

        $this->service->recordCacheHit('L2');
    }

    // ====================================================================
    // recordCacheMiss() Tests
    // ====================================================================

    #[Test]
    public function recordCacheMissRegistersCounterAndIncrements(): void
    {
        $counter = $this->createMock(Counter::class);
        $counter->expects($this->once())
            ->method('inc')
            ->with(['layer' => 'L2']);

        $this->registry->expects($this->once())
            ->method('getOrRegisterCounter')
            ->with('deschide_news', 'cache_misses_total', 'Total number of cache misses', ['layer'])
            ->willReturn($counter);

        $this->service->recordCacheMiss('L2');
    }

    #[Test]
    public function recordCacheMissHandlesException(): void
    {
        $this->registry->expects($this->once())
            ->method('getOrRegisterCounter')
            ->willThrowException(new Exception('Registry error'));

        // Should not throw
        $this->service->recordCacheMiss('L1');

        $this->assertTrue(true);
    }

    // ====================================================================
    // recordHttpRequestDuration() Tests
    // ====================================================================

    #[Test]
    public function recordHttpRequestDurationRegistersHistogramAndObserves(): void
    {
        $histogram = $this->createMock(Histogram::class);
        $histogram->expects($this->once())
            ->method('observe')
            ->with(0.125, ['/api/articles', 'true']);

        $this->registry->expects($this->once())
            ->method('getOrRegisterHistogram')
            ->with(
                'deschide_news',
                'http_request_duration_seconds',
                'HTTP request duration in seconds',
                ['path', 'cached'],
                [0.01, 0.05, 0.1, 0.2, 0.5, 1.0, 2.0, 5.0]
            )
            ->willReturn($histogram);

        $this->service->recordHttpRequestDuration('/api/articles', 0.125, true);
    }

    #[Test]
    public function recordHttpRequestDurationWithCachedFalse(): void
    {
        $histogram = $this->createMock(Histogram::class);
        $histogram->expects($this->once())
            ->method('observe')
            ->with(0.5, ['/api/categories', 'false']);

        $this->registry->method('getOrRegisterHistogram')->willReturn($histogram);

        $this->service->recordHttpRequestDuration('/api/categories', 0.5, false);
    }

    #[Test]
    public function recordHttpRequestDurationHandlesException(): void
    {
        $this->registry->expects($this->once())
            ->method('getOrRegisterHistogram')
            ->willThrowException(new Exception('Registry error'));

        // Should not throw
        $this->service->recordHttpRequestDuration('/api/articles', 0.5, true);

        $this->assertTrue(true);
    }

    // ====================================================================
    // recordPageView() Tests
    // ====================================================================

    #[Test]
    public function recordPageViewRegistersCounterAndIncrements(): void
    {
        $counter = $this->createMock(Counter::class);
        $counter->expects($this->once())
            ->method('inc');

        $this->registry->expects($this->once())
            ->method('getOrRegisterCounter')
            ->with('deschide_news', 'pageviews_total', 'Total number of page views')
            ->willReturn($counter);

        $this->service->recordPageView();
    }

    #[Test]
    public function recordPageViewHandlesException(): void
    {
        $this->registry->expects($this->once())
            ->method('getOrRegisterCounter')
            ->willThrowException(new Exception('Registry error'));

        // Should not throw
        $this->service->recordPageView();

        $this->assertTrue(true);
    }

    // ====================================================================
    // setCacheMemoryUsage() Tests
    // ====================================================================

    #[Test]
    public function setCacheMemoryUsageRegistersGaugeAndSetsValue(): void
    {
        $gauge = $this->createMock(Gauge::class);
        $gauge->expects($this->once())
            ->method('set')
            ->with(1048576, ['namespace' => 'cache']);

        $this->registry->expects($this->once())
            ->method('getOrRegisterGauge')
            ->with('deschide_news', 'cache_memory_bytes', 'Cache memory usage in bytes', ['namespace'])
            ->willReturn($gauge);

        $this->service->setCacheMemoryUsage('cache', 1048576);
    }

    #[Test]
    public function setCacheMemoryUsageHandlesException(): void
    {
        $this->registry->expects($this->once())
            ->method('getOrRegisterGauge')
            ->willThrowException(new Exception('Registry error'));

        // Should not throw
        $this->service->setCacheMemoryUsage('cache', 1048576);

        $this->assertTrue(true);
    }

    // ====================================================================
    // setActiveSessionsCount() Tests
    // ====================================================================

    #[Test]
    public function setActiveSessionsCountRegistersGaugeAndSetsValue(): void
    {
        $gauge = $this->createMock(Gauge::class);
        $gauge->expects($this->once())
            ->method('set')
            ->with(42, []);

        $this->registry->expects($this->once())
            ->method('getOrRegisterGauge')
            ->with('deschide_news', 'active_sessions_current', 'Current number of active sessions')
            ->willReturn($gauge);

        $this->service->setActiveSessionsCount(42);
    }

    #[Test]
    public function setActiveSessionsCountHandlesException(): void
    {
        $this->registry->expects($this->once())
            ->method('getOrRegisterGauge')
            ->willThrowException(new Exception('Registry error'));

        // Should not throw
        $this->service->setActiveSessionsCount(42);

        $this->assertTrue(true);
    }

    #[Test]
    public function setActiveSessionsCountWithZero(): void
    {
        $gauge = $this->createMock(Gauge::class);
        $gauge->expects($this->once())
            ->method('set')
            ->with(0, []);

        $this->registry->method('getOrRegisterGauge')->willReturn($gauge);

        $this->service->setActiveSessionsCount(0);
    }

    // ====================================================================
    // setUniqueVisitorsCount() Tests
    // ====================================================================

    #[Test]
    public function setUniqueVisitorsCountRegistersGaugeAndSetsValue(): void
    {
        $gauge = $this->createMock(Gauge::class);
        $gauge->expects($this->once())
            ->method('set')
            ->with(500, ['date' => '2026-03-27']);

        $this->registry->expects($this->once())
            ->method('getOrRegisterGauge')
            ->with('deschide_news', 'unique_visitors_total', 'Total unique visitors count', ['date'])
            ->willReturn($gauge);

        $this->service->setUniqueVisitorsCount('2026-03-27', 500);
    }

    #[Test]
    public function setUniqueVisitorsCountHandlesException(): void
    {
        $this->registry->expects($this->once())
            ->method('getOrRegisterGauge')
            ->willThrowException(new Exception('Registry error'));

        // Should not throw
        $this->service->setUniqueVisitorsCount('2026-03-27', 500);

        $this->assertTrue(true);
    }

    // ====================================================================
    // setArticleViews() Tests
    // ====================================================================

    #[Test]
    public function setArticleViewsRegistersGaugeAndSetsValue(): void
    {
        $gauge = $this->createMock(Gauge::class);
        $gauge->expects($this->once())
            ->method('set')
            ->with(150, ['article_id' => '42']);

        $this->registry->expects($this->once())
            ->method('getOrRegisterGauge')
            ->with('deschide_news', 'article_views_total', 'Total views per article', ['article_id'])
            ->willReturn($gauge);

        $this->service->setArticleViews(42, 150);
    }

    #[Test]
    public function setArticleViewsHandlesException(): void
    {
        $this->registry->expects($this->once())
            ->method('getOrRegisterGauge')
            ->willThrowException(new Exception('Registry error'));

        // Should not throw
        $this->service->setArticleViews(42, 150);

        $this->assertTrue(true);
    }

    // ====================================================================
    // updateCacheHitRate() Tests
    // ====================================================================

    #[Test]
    public function updateCacheHitRateGetsRedisMemoryInfoAndSetsGauge(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->with('info', ['memory'])
            ->willReturn(['Memory' => ['used_memory' => '2097152']]);

        // Two gauge calls: setCacheMemoryUsage + cache_hit_rate
        $gauge = $this->createMock(Gauge::class);
        $gauge->expects($this->exactly(2))
            ->method('set');

        $this->registry->expects($this->exactly(2))
            ->method('getOrRegisterGauge')
            ->willReturn($gauge);

        $this->service->updateCacheHitRate('L1');
    }

    #[Test]
    public function updateCacheHitRateHandlesMissingMemoryInfo(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->with('info', ['memory'])
            ->willReturn([]);

        // Only the cache_hit_rate gauge call (setCacheMemoryUsage is skipped)
        $gauge = $this->createMock(Gauge::class);
        $gauge->expects($this->once())
            ->method('set')
            ->with(0, ['layer' => 'L1']);

        $this->registry->expects($this->once())
            ->method('getOrRegisterGauge')
            ->willReturn($gauge);

        $this->service->updateCacheHitRate('L1');
    }

    #[Test]
    public function updateCacheHitRateHandlesException(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->willThrowException(new Exception('Redis error'));

        // Should not throw
        $this->service->updateCacheHitRate('L1');

        $this->assertTrue(true);
    }

    // ====================================================================
    // renderMetrics() Tests
    // ====================================================================

    #[Test]
    public function renderMetricsReturnsRenderedString(): void
    {
        $this->registry->expects($this->once())
            ->method('getMetricFamilySamples')
            ->willReturn([]);

        $result = $this->service->renderMetrics();

        // RenderTextFormat with empty samples returns an empty string
        $this->assertIsString($result);
    }

    #[Test]
    public function renderMetricsReturnsEmptyStringOnException(): void
    {
        $this->registry->expects($this->once())
            ->method('getMetricFamilySamples')
            ->willThrowException(new Exception('Registry error'));

        $result = $this->service->renderMetrics();

        $this->assertSame('', $result);
    }

    // ====================================================================
    // getActiveSessionCount() Tests
    // ====================================================================

    #[Test]
    public function getActiveSessionCountReturnsKeyCount(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->with('keys', ['deschide_news:stats:session:active:*'])
            ->willReturn(['key1', 'key2', 'key3']);

        $result = $this->service->getActiveSessionCount();

        $this->assertSame(3, $result);
    }

    #[Test]
    public function getActiveSessionCountReturnsZeroWhenNoKeys(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->with('keys', ['deschide_news:stats:session:active:*'])
            ->willReturn([]);

        $result = $this->service->getActiveSessionCount();

        $this->assertSame(0, $result);
    }

    #[Test]
    public function getActiveSessionCountReturnsZeroOnRedisError(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->willThrowException(new Exception('Redis error'));

        $result = $this->service->getActiveSessionCount();

        $this->assertSame(0, $result);
    }
}
