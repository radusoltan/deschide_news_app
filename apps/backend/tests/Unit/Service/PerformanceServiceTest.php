<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\Analytics\AnalyticsService;
use App\Service\Cache\CacheService;
use Exception;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Predis\Client;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for the split CacheService and AnalyticsService.
 *
 * Originally tested PerformanceService; now tests the two services it was split into (T38.4 SRP).
 */
class PerformanceServiceTest extends TestCase
{
    private Client $redis;
    private LoggerInterface $logger;
    private CacheService $cacheService;
    private AnalyticsService $analyticsService;

    protected function setUp(): void
    {
        $this->redis = $this->createMock(Client::class);
        $this->logger = $this->createStub(LoggerInterface::class);

        $this->cacheService = new CacheService(
            $this->redis,
            $this->logger,
        );

        $this->analyticsService = new AnalyticsService(
            $this->redis,
            $this->logger,
        );
    }

    // ====================================================================
    // getCached() Tests
    // ====================================================================

    #[Test]
    public function getCachedReturnsCachedValue(): void
    {
        $serialized = serialize(['id' => 1, 'title' => 'Test']);
        $this->redis->expects($this->once())
            ->method('__call')
            ->with('get', ['deschide_news:cache:my-key'])
            ->willReturn($serialized);

        $result = $this->cacheService->getCached('my-key');

        $this->assertSame(['id' => 1, 'title' => 'Test'], $result);
    }

    #[Test]
    public function getCachedReturnsNullOnCacheMiss(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->with('get', ['deschide_news:cache:articles:list'])
            ->willReturn(null);

        $result = $this->cacheService->getCached('articles:list');

        $this->assertNull($result);
    }

    #[Test]
    public function getCachedReturnsNullOnRedisException(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->with('get', ['deschide_news:cache:my-key'])
            ->willThrowException(new Exception('Redis unavailable'));

        $result = $this->cacheService->getCached('my-key');

        $this->assertNull($result);
    }

    #[Test]
    public function getCachedHandlesStringValue(): void
    {
        $serialized = serialize('simple string');
        $this->redis->expects($this->once())
            ->method('__call')
            ->with('get', ['deschide_news:cache:str-key'])
            ->willReturn($serialized);

        $result = $this->cacheService->getCached('str-key');

        $this->assertSame('simple string', $result);
    }

    #[Test]
    public function getCachedHandlesIntegerValue(): void
    {
        $serialized = serialize(42);
        $this->redis->expects($this->once())
            ->method('__call')
            ->with('get', ['deschide_news:cache:int-key'])
            ->willReturn($serialized);

        $result = $this->cacheService->getCached('int-key');

        $this->assertSame(42, $result);
    }

    // ====================================================================
    // setCached() Tests
    // ====================================================================

    #[Test]
    public function setCachedReturnsTrueOnSuccessOk(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->with('setex', ['deschide_news:cache:my-key', 3600, serialize('value')])
            ->willReturn('OK');

        $result = $this->cacheService->setCached('my-key', 'value', 3600);

        $this->assertTrue($result);
    }

    #[Test]
    public function setCachedReturnsTrueOnSuccessTrue(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->with('setex', ['deschide_news:cache:my-key', 300, serialize(['data' => true])])
            ->willReturn(true);

        $result = $this->cacheService->setCached('my-key', ['data' => true], 300);

        $this->assertTrue($result);
    }

    #[Test]
    public function setCachedReturnsFalseOnRedisError(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->with('setex', $this->anything())
            ->willThrowException(new Exception('Redis error'));

        $result = $this->cacheService->setCached('my-key', 'value', 3600);

        $this->assertFalse($result);
    }

    #[Test]
    public function setCachedReturnsFalseOnNonOkResult(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->with('setex', $this->anything())
            ->willReturn(null);

        $result = $this->cacheService->setCached('my-key', 'value', 3600);

        $this->assertFalse($result);
    }

    // ====================================================================
    // deleteCached() Tests
    // ====================================================================

    #[Test]
    public function deleteCachedReturnsTrueWhenKeyDeleted(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->with('del', [['deschide_news:cache:my-key']])
            ->willReturn(1);

        $result = $this->cacheService->deleteCached('my-key');

        $this->assertTrue($result);
    }

    #[Test]
    public function deleteCachedReturnsFalseWhenKeyNotFound(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->with('del', [['deschide_news:cache:nonexistent']])
            ->willReturn(0);

        $result = $this->cacheService->deleteCached('nonexistent');

        $this->assertFalse($result);
    }

    #[Test]
    public function deleteCachedReturnsFalseOnRedisError(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->willThrowException(new Exception('Redis error'));

        $result = $this->cacheService->deleteCached('my-key');

        $this->assertFalse($result);
    }

    // ====================================================================
    // deleteCachedPattern() Tests
    // ====================================================================

    #[Test]
    public function deleteCachedPatternDeletesMatchingKeys(): void
    {
        $keys = ['deschide_news:cache:api:articles:1:ro', 'deschide_news:cache:api:articles:1:en'];

        $this->redis->expects($this->exactly(2))
            ->method('__call')
            ->willReturnCallback(function (string $method, array $args) use ($keys) {
                if ($method === 'keys') {
                    $this->assertSame('deschide_news:cache:api:articles:1:*', $args[0]);
                    return $keys;
                }
                if ($method === 'del') {
                    $this->assertSame($keys, $args[0]);
                    return 2;
                }
                return null;
            });

        $result = $this->cacheService->deleteCachedPattern('api:articles:1:*');

        $this->assertSame(2, $result);
    }

    #[Test]
    public function deleteCachedPatternReturnsZeroWhenNoKeysMatch(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->with('keys', ['deschide_news:cache:api:nonexistent:*'])
            ->willReturn([]);

        $result = $this->cacheService->deleteCachedPattern('api:nonexistent:*');

        $this->assertSame(0, $result);
    }

    #[Test]
    public function deleteCachedPatternReturnsZeroOnRedisError(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->with('keys', $this->anything())
            ->willThrowException(new Exception('Redis error'));

        $result = $this->cacheService->deleteCachedPattern('api:*');

        $this->assertSame(0, $result);
    }

    // ====================================================================
    // invalidateArticle() Tests
    // ====================================================================

    #[Test]
    public function invalidateArticleDeletesCorrectPatterns(): void
    {
        $callIndex = 0;

        $this->redis->expects($this->atLeast(3))
            ->method('__call')
            ->willReturnCallback(function (string $method, array $args) use (&$callIndex) {
                $callIndex++;
                if ($method === 'keys') {
                    return [];
                }
                if ($method === 'del') {
                    return 1;
                }
                return null;
            });

        $this->cacheService->invalidateArticle(42);
    }

    // ====================================================================
    // invalidateCategory() Tests
    // ====================================================================

    #[Test]
    public function invalidateCategoryDeletesCorrectPatterns(): void
    {
        $keysCallPatterns = [];

        $this->redis->expects($this->atLeast(3))
            ->method('__call')
            ->willReturnCallback(function (string $method, array $args) use (&$keysCallPatterns) {
                if ($method === 'keys') {
                    $keysCallPatterns[] = $args[0];
                    return [];
                }
                if ($method === 'del') {
                    return 0;
                }
                return null;
            });

        $this->cacheService->invalidateCategory(5);

        $this->assertContains('deschide_news:cache:api:categories:5:*', $keysCallPatterns);
        $this->assertContains('deschide_news:cache:api:categories:list:*', $keysCallPatterns);
        $this->assertContains('deschide_news:cache:api:articles:list:*', $keysCallPatterns);
    }

    // ====================================================================
    // incrementArticleViews() Tests
    // ====================================================================

    #[Test]
    public function incrementArticleViewsCallsIncrAndZincrby(): void
    {
        $calls = [];

        $this->redis->expects($this->exactly(3))
            ->method('__call')
            ->willReturnCallback(function (string $method, array $args) use (&$calls) {
                $calls[] = [$method, $args];
                if ($method === 'incr') {
                    return 1;
                }
                if ($method === 'zincrby') {
                    return 1.0;
                }
                if ($method === 'expire') {
                    return true;
                }
                return null;
            });

        $this->analyticsService->incrementArticleViews(42);

        $this->assertSame('incr', $calls[0][0]);
        $this->assertSame('deschide_news:stats:article:views:42', $calls[0][1][0]);
        $this->assertSame('zincrby', $calls[1][0]);
        $this->assertSame('deschide_news:stats:trending:24h', $calls[1][1][0]);
        $this->assertSame(1, $calls[1][1][1]);
        $this->assertSame('article:42', $calls[1][1][2]);
        $this->assertSame('expire', $calls[2][0]);
    }

    #[Test]
    public function incrementArticleViewsHandlesRedisError(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->willThrowException(new Exception('Redis error'));

        $this->analyticsService->incrementArticleViews(42);

        $this->assertTrue(true);
    }

    // ====================================================================
    // trackUniqueVisitor() Tests
    // ====================================================================

    #[Test]
    public function trackUniqueVisitorReturnsTrueForNewVisitor(): void
    {
        $this->redis->expects($this->exactly(2))
            ->method('__call')
            ->willReturnCallback(function (string $method, array $args) {
                if ($method === 'sadd') {
                    $this->assertStringContainsString('article:visitors:42:', $args[0]);
                    $this->assertSame(['visitor-123'], $args[1]);
                    return 1;
                }
                if ($method === 'expire') {
                    return true;
                }
                return null;
            });

        $result = $this->analyticsService->trackUniqueVisitor(42, 'visitor-123');

        $this->assertTrue($result);
    }

    #[Test]
    public function trackUniqueVisitorReturnsFalseForExistingVisitor(): void
    {
        $this->redis->expects($this->exactly(2))
            ->method('__call')
            ->willReturnCallback(function (string $method) {
                if ($method === 'sadd') {
                    return 0;
                }
                if ($method === 'expire') {
                    return true;
                }
                return null;
            });

        $result = $this->analyticsService->trackUniqueVisitor(42, 'visitor-123');

        $this->assertFalse($result);
    }

    #[Test]
    public function trackUniqueVisitorReturnsFalseOnRedisError(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->willThrowException(new Exception('Redis error'));

        $result = $this->analyticsService->trackUniqueVisitor(42, 'visitor-123');

        $this->assertFalse($result);
    }

    // ====================================================================
    // trackSiteVisitor() Tests
    // ====================================================================

    #[Test]
    public function trackSiteVisitorCallsPfaddAndExpire(): void
    {
        $calls = [];

        $this->redis->expects($this->exactly(2))
            ->method('__call')
            ->willReturnCallback(function (string $method, array $args) use (&$calls) {
                $calls[] = [$method, $args];
                if ($method === 'pfadd') {
                    $this->assertStringContainsString('site:visitors:', $args[0]);
                    $this->assertSame(['visitor-abc'], $args[1]);
                    return 1;
                }
                if ($method === 'expire') {
                    return true;
                }
                return null;
            });

        $this->analyticsService->trackSiteVisitor('visitor-abc');

        $this->assertSame('pfadd', $calls[0][0]);
        $this->assertSame('expire', $calls[1][0]);
    }

    #[Test]
    public function trackSiteVisitorHandlesRedisError(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->willThrowException(new Exception('Redis error'));

        $this->analyticsService->trackSiteVisitor('visitor-abc');

        $this->assertTrue(true);
    }

    // ====================================================================
    // getArticleViews() Tests
    // ====================================================================

    #[Test]
    public function getArticleViewsReturnsIntegerValue(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->with('get', ['deschide_news:stats:article:views:42'])
            ->willReturn('150');

        $result = $this->analyticsService->getArticleViews(42);

        $this->assertSame(150, $result);
    }

    #[Test]
    public function getArticleViewsReturnsZeroWhenKeyNotFound(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->with('get', ['deschide_news:stats:article:views:99'])
            ->willReturn(null);

        $result = $this->analyticsService->getArticleViews(99);

        $this->assertSame(0, $result);
    }

    #[Test]
    public function getArticleViewsReturnsZeroWhenValueIsFalsy(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->with('get', ['deschide_news:stats:article:views:99'])
            ->willReturn('0');

        $result = $this->analyticsService->getArticleViews(99);

        $this->assertSame(0, $result);
    }

    #[Test]
    public function getArticleViewsReturnsZeroOnRedisError(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->willThrowException(new Exception('Redis error'));

        $result = $this->analyticsService->getArticleViews(42);

        $this->assertSame(0, $result);
    }

    // ====================================================================
    // getUniqueVisitorCount() Tests
    // ====================================================================

    #[Test]
    public function getUniqueVisitorCountReturnsCount(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->with('pfcount', [['deschide_news:stats:site:visitors:2026-03-27']])
            ->willReturn(500);

        $result = $this->analyticsService->getUniqueVisitorCount('2026-03-27');

        $this->assertSame(500, $result);
    }

    #[Test]
    public function getUniqueVisitorCountReturnsZeroOnRedisError(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->willThrowException(new Exception('Redis error'));

        $result = $this->analyticsService->getUniqueVisitorCount('2026-03-27');

        $this->assertSame(0, $result);
    }

    // ====================================================================
    // getTrendingArticles() Tests
    // ====================================================================

    #[Test]
    public function getTrendingArticlesReturnsCorrectlyParsedResults(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->with('zrevrange', ['deschide_news:stats:trending:24h', 0, 9, ['WITHSCORES' => true]])
            ->willReturn([
                'article:42' => '150',
                'article:7' => '100',
                'article:99' => '50',
            ]);

        $result = $this->analyticsService->getTrendingArticles(10);

        $this->assertCount(3, $result);
        $this->assertSame(['article_id' => 42, 'views' => 150], $result[0]);
        $this->assertSame(['article_id' => 7, 'views' => 100], $result[1]);
        $this->assertSame(['article_id' => 99, 'views' => 50], $result[2]);
    }

    #[Test]
    public function getTrendingArticlesIgnoresNonArticleMembers(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->willReturn([
                'article:42' => '150',
                'non_article_key' => '100',
                'article:7' => '50',
            ]);

        $result = $this->analyticsService->getTrendingArticles(10);

        $this->assertCount(2, $result);
        $this->assertSame(42, $result[0]['article_id']);
        $this->assertSame(7, $result[1]['article_id']);
    }

    #[Test]
    public function getTrendingArticlesReturnsEmptyOnRedisError(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->willThrowException(new Exception('Redis error'));

        $result = $this->analyticsService->getTrendingArticles(10);

        $this->assertSame([], $result);
    }

    #[Test]
    public function getTrendingArticlesReturnsEmptyWhenNoData(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->willReturn([]);

        $result = $this->analyticsService->getTrendingArticles(10);

        $this->assertSame([], $result);
    }

    #[Test]
    public function getTrendingArticlesUsesCorrectLimit(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->with('zrevrange', ['deschide_news:stats:trending:24h', 0, 4, ['WITHSCORES' => true]])
            ->willReturn([]);

        $this->analyticsService->getTrendingArticles(5);
    }

    #[Test]
    public function getTrendingArticlesDefaultsToTen(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->with('zrevrange', ['deschide_news:stats:trending:24h', 0, 9, ['WITHSCORES' => true]])
            ->willReturn([]);

        $this->analyticsService->getTrendingArticles();
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

        $result = $this->analyticsService->getActiveSessionCount();

        $this->assertSame(3, $result);
    }

    #[Test]
    public function getActiveSessionCountReturnsZeroWhenNoSessions(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->with('keys', ['deschide_news:stats:session:active:*'])
            ->willReturn([]);

        $result = $this->analyticsService->getActiveSessionCount();

        $this->assertSame(0, $result);
    }

    #[Test]
    public function getActiveSessionCountReturnsZeroOnRedisError(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->willThrowException(new Exception('Redis error'));

        $result = $this->analyticsService->getActiveSessionCount();

        $this->assertSame(0, $result);
    }

    #[Test]
    public function getActiveSessionCountReturnsZeroWhenKeysReturnsNonArray(): void
    {
        $this->redis->expects($this->once())
            ->method('__call')
            ->with('keys', ['deschide_news:stats:session:active:*'])
            ->willReturn(false);

        $result = $this->analyticsService->getActiveSessionCount();

        $this->assertSame(0, $result);
    }
}
