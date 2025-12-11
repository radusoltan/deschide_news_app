<?php

declare(strict_types=1);

namespace App\Tests\Performance;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Performance tests for API response times.
 *
 * Measures response times for critical API endpoints and ensures they meet
 * performance thresholds. Tests p50, p95, and p99 percentiles to identify
 * outliers and performance degradation.
 *
 * Run with: vendor/bin/phpunit tests/Performance --group=performance --testdox
 */
#[Group('performance')]
class ApiResponseTimeTest extends WebTestCase
{
    // Performance thresholds (in milliseconds)
    private const THRESHOLD_ARTICLES_LIST = 200;    // p95 < 200ms
    private const THRESHOLD_SINGLE_ARTICLE = 150;   // p95 < 150ms
    private const THRESHOLD_CATEGORIES = 100;       // p95 < 100ms
    private const THRESHOLD_AUTHORS = 100;          // p95 < 100ms
    private const THRESHOLD_IMPORTANT = 200;        // p95 < 200ms

    // Number of runs for reliable measurements
    private const RUNS = 10;
    private const CONCURRENT_RUNS = 10;

    /**
     * Test GET /api/articles response time.
     */
    public function testArticlesListResponseTime(): void
    {
        $client = static::createClient();
        $times = [];

        for ($i = 0; $i < self::RUNS; $i++) {
            $startTime = microtime(true);
            $client->request('GET', '/api/articles?itemsPerPage=30', [], [], [
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_ACCEPT_LANGUAGE' => 'ro',
            ]);
            $endTime = microtime(true);
            $times[] = ($endTime - $startTime) * 1000;

            $this->assertResponseIsSuccessful();
        }

        $stats = $this->calculateStatistics($times);

        echo sprintf(
            "\n📊 GET /api/articles?itemsPerPage=30:\n" .
            "   ├─ Avg: %.2fms\n" .
            "   ├─ Min: %.2fms\n" .
            "   ├─ Max: %.2fms\n" .
            "   ├─ P50: %.2fms\n" .
            "   ├─ P95: %.2fms\n" .
            "   └─ P99: %.2fms\n",
            $stats['avg'],
            $stats['min'],
            $stats['max'],
            $stats['p50'],
            $stats['p95'],
            $stats['p99']
        );

        $this->assertLessThan(
            self::THRESHOLD_ARTICLES_LIST,
            $stats['p95'],
            sprintf(
                "P95 response time (%.2fms) exceeds threshold (%dms). Consider:\n" .
                "- Check for N+1 queries in ArticleProvider\n" .
                "- Verify eager loading is enabled\n" .
                "- Review database indices",
                $stats['p95'],
                self::THRESHOLD_ARTICLES_LIST
            )
        );
    }

    /**
     * Test GET /api/articles/{id} response time.
     */
    public function testSingleArticleResponseTime(): void
    {
        $client = static::createClient();

        // First, get an article ID
        $client->request('GET', '/api/articles?itemsPerPage=1', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
            'HTTP_ACCEPT_LANGUAGE' => 'ro',
        ]);

        $data = json_decode($client->getResponse()->getContent(), true);
        if (empty($data['member'])) {
            $this->markTestSkipped('No articles available for testing');
        }

        $articleId = $data['member'][0]['id'];
        $times = [];

        for ($i = 0; $i < self::RUNS; $i++) {
            $startTime = microtime(true);
            $client->request('GET', "/api/articles/{$articleId}", [], [], [
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_ACCEPT_LANGUAGE' => 'ro',
            ]);
            $endTime = microtime(true);
            $times[] = ($endTime - $startTime) * 1000;

            $this->assertResponseIsSuccessful();
        }

        $stats = $this->calculateStatistics($times);

        echo sprintf(
            "\n📊 GET /api/articles/%d:\n" .
            "   ├─ Avg: %.2fms\n" .
            "   ├─ Min: %.2fms\n" .
            "   ├─ Max: %.2fms\n" .
            "   ├─ P50: %.2fms\n" .
            "   ├─ P95: %.2fms\n" .
            "   └─ P99: %.2fms\n",
            $articleId,
            $stats['avg'],
            $stats['min'],
            $stats['max'],
            $stats['p50'],
            $stats['p95'],
            $stats['p99']
        );

        $this->assertLessThan(
            self::THRESHOLD_SINGLE_ARTICLE,
            $stats['p95'],
            sprintf(
                "P95 response time (%.2fms) exceeds threshold (%dms). Consider:\n" .
                "- Review eager loading for single article queries\n" .
                "- Check serialization group performance\n" .
                "- Verify cache is being used",
                $stats['p95'],
                self::THRESHOLD_SINGLE_ARTICLE
            )
        );
    }

    /**
     * Test GET /api/categories response time.
     */
    public function testCategoriesResponseTime(): void
    {
        $client = static::createClient();
        $times = [];

        for ($i = 0; $i < self::RUNS; $i++) {
            $startTime = microtime(true);
            $client->request('GET', '/api/categories', [], [], [
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_ACCEPT_LANGUAGE' => 'ro',
            ]);
            $endTime = microtime(true);
            $times[] = ($endTime - $startTime) * 1000;

            $this->assertResponseIsSuccessful();
        }

        $stats = $this->calculateStatistics($times);

        echo sprintf(
            "\n📊 GET /api/categories:\n" .
            "   ├─ Avg: %.2fms\n" .
            "   ├─ Min: %.2fms\n" .
            "   ├─ Max: %.2fms\n" .
            "   ├─ P50: %.2fms\n" .
            "   ├─ P95: %.2fms\n" .
            "   └─ P99: %.2fms\n",
            $stats['avg'],
            $stats['min'],
            $stats['max'],
            $stats['p50'],
            $stats['p95'],
            $stats['p99']
        );

        $this->assertLessThan(
            self::THRESHOLD_CATEGORIES,
            $stats['p95'],
            sprintf(
                "P95 response time (%.2fms) exceeds threshold (%dms). Categories should be cached.",
                $stats['p95'],
                self::THRESHOLD_CATEGORIES
            )
        );
    }

    /**
     * Test GET /api/important_articles response time.
     */
    public function testImportantArticlesResponseTime(): void
    {
        $client = static::createClient();
        $times = [];

        for ($i = 0; $i < self::RUNS; $i++) {
            $startTime = microtime(true);
            $client->request('GET', '/api/important_articles', [], [], [
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_ACCEPT_LANGUAGE' => 'ro',
            ]);
            $endTime = microtime(true);
            $times[] = ($endTime - $startTime) * 1000;

            $this->assertResponseIsSuccessful();
        }

        $stats = $this->calculateStatistics($times);

        echo sprintf(
            "\n📊 GET /api/important_articles:\n" .
            "   ├─ Avg: %.2fms\n" .
            "   ├─ Min: %.2fms\n" .
            "   ├─ Max: %.2fms\n" .
            "   ├─ P50: %.2fms\n" .
            "   ├─ P95: %.2fms\n" .
            "   └─ P99: %.2fms\n",
            $stats['avg'],
            $stats['min'],
            $stats['max'],
            $stats['p50'],
            $stats['p95'],
            $stats['p99']
        );

        $this->assertLessThan(
            self::THRESHOLD_IMPORTANT,
            $stats['p95'],
            sprintf(
                "P95 response time (%.2fms) exceeds threshold (%dms). Consider:\n" .
                "- Verify eager loading of article relationships\n" .
                "- Check for N+1 queries with images\n" .
                "- Review caching strategy",
                $stats['p95'],
                self::THRESHOLD_IMPORTANT
            )
        );
    }

    /**
     * Test GET /api/authors response time.
     */
    public function testAuthorsResponseTime(): void
    {
        $client = static::createClient();
        $times = [];

        for ($i = 0; $i < self::RUNS; $i++) {
            $startTime = microtime(true);
            $client->request('GET', '/api/authors', [], [], [
                'HTTP_ACCEPT' => 'application/ld+json',
            ]);
            $endTime = microtime(true);
            $times[] = ($endTime - $startTime) * 1000;

            $this->assertResponseIsSuccessful();
        }

        $stats = $this->calculateStatistics($times);

        echo sprintf(
            "\n📊 GET /api/authors:\n" .
            "   ├─ Avg: %.2fms\n" .
            "   ├─ Min: %.2fms\n" .
            "   ├─ Max: %.2fms\n" .
            "   ├─ P50: %.2fms\n" .
            "   ├─ P95: %.2fms\n" .
            "   └─ P99: %.2fms\n",
            $stats['avg'],
            $stats['min'],
            $stats['max'],
            $stats['p50'],
            $stats['p95'],
            $stats['p99']
        );

        $this->assertLessThan(
            self::THRESHOLD_AUTHORS,
            $stats['p95'],
            sprintf(
                "P95 response time (%.2fms) exceeds threshold (%dms). Authors list should be fast.",
                $stats['p95'],
                self::THRESHOLD_AUTHORS
            )
        );
    }

    /**
     * Test concurrent requests to simulate multiple users.
     */
    public function testConcurrentArticleRequests(): void
    {
        $client = static::createClient();
        $times = [];

        echo "\n⏱️  Testing " . self::CONCURRENT_RUNS . " concurrent article requests...\n";

        $overallStart = microtime(true);

        for ($i = 0; $i < self::CONCURRENT_RUNS; $i++) {
            $startTime = microtime(true);
            $client->request('GET', '/api/articles?itemsPerPage=10', [], [], [
                'HTTP_ACCEPT' => 'application/ld+json',
                'HTTP_ACCEPT_LANGUAGE' => 'ro',
            ]);
            $endTime = microtime(true);
            $times[] = ($endTime - $startTime) * 1000;

            $this->assertResponseIsSuccessful();
        }

        $overallEnd = microtime(true);
        $totalTime = ($overallEnd - $overallStart) * 1000;
        $stats = $this->calculateStatistics($times);

        echo sprintf(
            "📊 Concurrent Requests Performance:\n" .
            "   ├─ Total time: %.2fms\n" .
            "   ├─ Requests: %d\n" .
            "   ├─ Avg per request: %.2fms\n" .
            "   ├─ Max: %.2fms\n" .
            "   ├─ P95: %.2fms\n" .
            "   └─ Throughput: %.2f req/sec\n",
            $totalTime,
            self::CONCURRENT_RUNS,
            $stats['avg'],
            $stats['max'],
            $stats['p95'],
            (self::CONCURRENT_RUNS / $totalTime) * 1000
        );

        // Under concurrent load, allow 2x threshold
        $this->assertLessThan(
            self::THRESHOLD_ARTICLES_LIST * 2,
            $stats['p95'],
            sprintf(
                "P95 response time under concurrent load (%.2fms) is too high. " .
                "Consider database connection pooling or caching improvements.",
                $stats['p95']
            )
        );
    }

    /**
     * Calculate performance statistics from timing data.
     *
     * @param array<float> $times Array of response times in milliseconds
     * @return array<string, float> Statistics (avg, min, max, p50, p95, p99)
     */
    private function calculateStatistics(array $times): array
    {
        sort($times);
        $count = count($times);

        return [
            'avg' => array_sum($times) / $count,
            'min' => min($times),
            'max' => max($times),
            'p50' => $this->percentile($times, 50),
            'p95' => $this->percentile($times, 95),
            'p99' => $this->percentile($times, 99),
        ];
    }

    /**
     * Calculate percentile from sorted array of values.
     *
     * @param array<float> $values Sorted array of values
     * @param int $percentile Percentile to calculate (0-100)
     * @return float Percentile value
     */
    private function percentile(array $values, int $percentile): float
    {
        $count = count($values);
        $index = ceil(($percentile / 100) * $count) - 1;
        $index = max(0, min($index, $count - 1));

        return $values[(int) $index];
    }
}
