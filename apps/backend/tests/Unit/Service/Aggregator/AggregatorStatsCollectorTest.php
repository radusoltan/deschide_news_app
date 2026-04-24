<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Aggregator;

use App\Repository\PressReleaseRepository;
use App\Service\Aggregator\AggregatorStatsCollector;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[CoversClass(AggregatorStatsCollector::class)]
class AggregatorStatsCollectorTest extends TestCase
{
    private TagAwareCacheInterface&MockObject $cache;
    private PressReleaseRepository&MockObject $repository;
    private AggregatorStatsCollector $collector;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(TagAwareCacheInterface::class);
        $this->repository = $this->createMock(PressReleaseRepository::class);

        $this->collector = new AggregatorStatsCollector(
            $this->cache,
            $this->repository,
        );
    }

    public function testCollectAndCacheStatsReturnsProperStructure(): void
    {
        $this->mockRepositoryResults([
            [
                'source_name' => 'aggregator:google_news_rss',
                'articles_found' => 42,
                'pending_review' => 12,
                'last_run' => new \DateTimeImmutable('-1 hour'),
            ],
        ]);

        // cache->delete should be called first
        $this->cache->expects($this->once())->method('delete');

        // cache->get should invoke the callback (simulating cache miss)
        $this->cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->expects($this->once())->method('expiresAfter')->with(86400);
                $item->expects($this->once())->method('tag')->with('aggregator_stats');

                return $callback($item);
            });

        $stats = $this->collector->collectAndCacheStats();

        $this->assertIsArray($stats);
        $this->assertCount(1, $stats);

        $entry = $stats[0];
        $this->assertSame('aggregator:google_news_rss', $entry['source']);
        $this->assertSame(42, $entry['articlesFound']);
        $this->assertSame(12, $entry['pendingReview']);
        $this->assertSame(0, $entry['duplicatesSkipped']);
        $this->assertSame('healthy', $entry['status']);
        $this->assertNotNull($entry['lastRun']);
    }

    public function testEmptyResultsReturnsEmptyArray(): void
    {
        $this->mockRepositoryResults([]);

        $this->cache->expects($this->once())->method('delete');
        $this->cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->method('expiresAfter');
                $item->method('tag');

                return $callback($item);
            });

        $stats = $this->collector->collectAndCacheStats();

        $this->assertSame([], $stats);
    }

    public function testHealthStatusHealthyWhenRecentRun(): void
    {
        $this->mockRepositoryResults([
            [
                'source_name' => 'aggregator:test',
                'articles_found' => 5,
                'pending_review' => 1,
                'last_run' => new \DateTimeImmutable('-30 minutes'),
            ],
        ]);

        $this->cache->method('delete');
        $this->cache->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->method('expiresAfter');
                $item->method('tag');

                return $callback($item);
            });

        $stats = $this->collector->collectAndCacheStats();

        $this->assertSame('healthy', $stats[0]['status']);
    }

    public function testHealthStatusWarningWhenRunOver3HoursAgo(): void
    {
        $this->mockRepositoryResults([
            [
                'source_name' => 'aggregator:test',
                'articles_found' => 5,
                'pending_review' => 1,
                'last_run' => new \DateTimeImmutable('-4 hours'),
            ],
        ]);

        $this->cache->method('delete');
        $this->cache->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->method('expiresAfter');
                $item->method('tag');

                return $callback($item);
            });

        $stats = $this->collector->collectAndCacheStats();

        $this->assertSame('warning', $stats[0]['status']);
    }

    public function testHealthStatusErrorWhenRunOver6HoursAgo(): void
    {
        $this->mockRepositoryResults([
            [
                'source_name' => 'aggregator:test',
                'articles_found' => 10,
                'pending_review' => 3,
                'last_run' => new \DateTimeImmutable('-7 hours'),
            ],
        ]);

        $this->cache->method('delete');
        $this->cache->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->method('expiresAfter');
                $item->method('tag');

                return $callback($item);
            });

        $stats = $this->collector->collectAndCacheStats();

        $this->assertSame('error', $stats[0]['status']);
    }

    public function testHealthStatusErrorWhenLastRunIsNull(): void
    {
        $this->mockRepositoryResults([
            [
                'source_name' => 'aggregator:test',
                'articles_found' => 2,
                'pending_review' => 0,
                'last_run' => null,
            ],
        ]);

        $this->cache->method('delete');
        $this->cache->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->method('expiresAfter');
                $item->method('tag');

                return $callback($item);
            });

        $stats = $this->collector->collectAndCacheStats();

        $this->assertSame('error', $stats[0]['status']);
        $this->assertNull($stats[0]['lastRun']);
    }

    public function testMultipleSourcesReturnedSorted(): void
    {
        $this->mockRepositoryResults([
            [
                'source_name' => 'aggregator:google_news_rss',
                'articles_found' => 42,
                'pending_review' => 12,
                'last_run' => new \DateTimeImmutable('-1 hour'),
            ],
            [
                'source_name' => 'aggregator:bing_news',
                'articles_found' => 15,
                'pending_review' => 5,
                'last_run' => new \DateTimeImmutable('-5 hours'),
            ],
        ]);

        $this->cache->method('delete');
        $this->cache->method('get')
            ->willReturnCallback(function (string $key, callable $callback) {
                $item = $this->createMock(ItemInterface::class);
                $item->method('expiresAfter');
                $item->method('tag');

                return $callback($item);
            });

        $stats = $this->collector->collectAndCacheStats();

        $this->assertCount(2, $stats);
        $this->assertSame('aggregator:google_news_rss', $stats[0]['source']);
        $this->assertSame('aggregator:bing_news', $stats[1]['source']);
        $this->assertSame('healthy', $stats[0]['status']);
        $this->assertSame('warning', $stats[1]['status']);
    }

    public function testInvalidateCacheCallsTagInvalidation(): void
    {
        $this->cache->expects($this->once())
            ->method('invalidateTags')
            ->with(['aggregator_stats']);

        $this->collector->invalidateCache();
    }

    private function mockRepositoryResults(array $rows): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getArrayResult')->willReturn($rows);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('groupBy')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->repository->method('createQueryBuilder')->willReturn($qb);
    }
}
