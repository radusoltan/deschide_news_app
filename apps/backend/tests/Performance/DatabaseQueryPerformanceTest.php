<?php

declare(strict_types=1);

namespace App\Tests\Performance;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Performance tests for database query optimization.
 *
 * Detects N+1 query problems, excessive query counts, and slow queries.
 * Ensures that eager loading is properly configured and database operations
 * are optimized.
 *
 * Run with: vendor/bin/phpunit tests/Performance --group=performance --testdox
 */
#[Group('performance')]
class DatabaseQueryPerformanceTest extends WebTestCase
{
    // Maximum acceptable query count per request
    private const MAX_QUERIES_ARTICLES_LIST = 10;
    private const MAX_QUERIES_SINGLE_ARTICLE = 5;
    private const MAX_QUERIES_CATEGORIES = 3;
    private const MAX_QUERIES_IMPORTANT = 10;

    /**
     * Test query count for GET /api/articles.
     *
     * This endpoint should use eager loading to fetch articles with their
     * relationships (category, author, images) in a minimal number of queries.
     */
    public function testArticlesListQueryCount(): void
    {
        $client = static::createClient();
        $client->enableProfiler();

        $client->request('GET', '/api/articles?itemsPerPage=10', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
            'HTTP_ACCEPT_LANGUAGE' => 'ro',
        ]);

        $this->assertResponseIsSuccessful();

        $profile = $client->getProfile();
        $this->assertNotNull($profile, 'Profiler must be enabled in test environment');

        if ($profile->hasCollector('db')) {
            $dbCollector = $profile->getCollector('db');
            $queryCount = $dbCollector->getQueryCount();
            $queries = $dbCollector->getQueries();

            echo sprintf(
                "\n📊 GET /api/articles?itemsPerPage=10:\n" .
                "   ├─ Total queries: %d\n" .
                "   ├─ Query time: %.2fms\n" .
                "   └─ Threshold: %d queries\n",
                $queryCount,
                $dbCollector->getTime() * 1000,
                self::MAX_QUERIES_ARTICLES_LIST
            );

            // Show all queries for debugging
            if ($queryCount > 0 && !empty($queries)) {
                echo "   \n   Queries executed:\n";
                foreach ($queries as $i => $query) {
                    if (isset($query['sql'])) {
                        $executionTime = $query['executionMS'] ?? 0;
                        echo sprintf("   %d. %s [%.2fms]\n", $i + 1, $this->simplifyQuery($query['sql']), $executionTime);
                    }
                }
                echo "\n";
            }

            $this->assertLessThanOrEqual(
                self::MAX_QUERIES_ARTICLES_LIST,
                $queryCount,
                sprintf(
                    "Too many queries for articles list! Expected <= %d, got %d.\n" .
                    "Common issues:\n" .
                    "- N+1 problem: Missing eager loading in ArticleProvider\n" .
                    "- Lazy loading triggered by serialization\n" .
                    "- Missing JOIN with addSelect() in query builder\n" .
                    "Fix: Add leftJoin() + addSelect() for all relationships",
                    self::MAX_QUERIES_ARTICLES_LIST,
                    $queryCount
                )
            );

            // Detect N+1 queries by looking for repeated similar queries
            $this->detectN1Queries($queries);
        } else {
            $this->markTestSkipped('Database profiler not available');
        }
    }

    /**
     * Test query count for GET /api/articles/{id}.
     *
     * Single article fetch should be even more efficient than list fetch.
     */
    public function testSingleArticleQueryCount(): void
    {
        $client = static::createClient();
        $client->enableProfiler();

        // First get an article ID
        $client->request('GET', '/api/articles?itemsPerPage=1', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
            'HTTP_ACCEPT_LANGUAGE' => 'ro',
        ]);

        $data = json_decode($client->getResponse()->getContent(), true);
        if (empty($data['member'])) {
            $this->markTestSkipped('No articles available for testing');
        }

        $articleId = $data['member'][0]['id'];

        // Now test single article fetch
        $client->request('GET', "/api/articles/{$articleId}", [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
            'HTTP_ACCEPT_LANGUAGE' => 'ro',
        ]);

        $this->assertResponseIsSuccessful();

        $profile = $client->getProfile();
        if ($profile === null || $profile === false) {
            $this->markTestSkipped('Profiler not available in test environment');
        }

        if ($profile->hasCollector('db')) {
            $dbCollector = $profile->getCollector('db');
            $queryCount = $dbCollector->getQueryCount();
            $queries = $dbCollector->getQueries();

            echo sprintf(
                "\n📊 GET /api/articles/%d:\n" .
                "   ├─ Total queries: %d\n" .
                "   ├─ Query time: %.2fms\n" .
                "   └─ Threshold: %d queries\n",
                $articleId,
                $queryCount,
                $dbCollector->getTime() * 1000,
                self::MAX_QUERIES_SINGLE_ARTICLE
            );

            if ($queryCount > 0 && !empty($queries)) {
                echo "   \n   Queries executed:\n";
                foreach ($queries as $i => $query) {
                    if (isset($query['sql'])) {
                        $executionTime = $query['executionMS'] ?? 0;
                        echo sprintf("   %d. %s [%.2fms]\n", $i + 1, $this->simplifyQuery($query['sql']), $executionTime);
                    }
                }
                echo "\n";
            }

            $this->assertLessThanOrEqual(
                self::MAX_QUERIES_SINGLE_ARTICLE,
                $queryCount,
                sprintf(
                    "Too many queries for single article! Expected <= %d, got %d.",
                    self::MAX_QUERIES_SINGLE_ARTICLE,
                    $queryCount
                )
            );
        } else {
            $this->markTestSkipped('Database profiler not available');
        }
    }

    /**
     * Test query count for GET /api/categories.
     */
    public function testCategoriesQueryCount(): void
    {
        $client = static::createClient();
        $client->enableProfiler();

        $client->request('GET', '/api/categories', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
            'HTTP_ACCEPT_LANGUAGE' => 'ro',
        ]);

        $this->assertResponseIsSuccessful();

        $profile = $client->getProfile();
        $this->assertNotNull($profile, 'Profiler must be enabled');

        if ($profile->hasCollector('db')) {
            $dbCollector = $profile->getCollector('db');
            $queryCount = $dbCollector->getQueryCount();

            echo sprintf(
                "\n📊 GET /api/categories:\n" .
                "   ├─ Total queries: %d\n" .
                "   ├─ Query time: %.2fms\n" .
                "   └─ Threshold: %d queries\n",
                $queryCount,
                $dbCollector->getTime() * 1000,
                self::MAX_QUERIES_CATEGORIES
            );

            $this->assertLessThanOrEqual(
                self::MAX_QUERIES_CATEGORIES,
                $queryCount,
                sprintf(
                    "Too many queries for categories! Expected <= %d, got %d. " .
                    "Categories should be simple and cacheable.",
                    self::MAX_QUERIES_CATEGORIES,
                    $queryCount
                )
            );
        } else {
            $this->markTestSkipped('Database profiler not available');
        }
    }

    /**
     * Test query count for GET /api/important_articles.
     */
    public function testImportantArticlesQueryCount(): void
    {
        $client = static::createClient();
        $client->enableProfiler();

        $client->request('GET', '/api/important_articles', [], [], [
            'HTTP_ACCEPT' => 'application/ld+json',
            'HTTP_ACCEPT_LANGUAGE' => 'ro',
        ]);

        $this->assertResponseIsSuccessful();

        $profile = $client->getProfile();
        $this->assertNotNull($profile, 'Profiler must be enabled');

        if ($profile->hasCollector('db')) {
            $dbCollector = $profile->getCollector('db');
            $queryCount = $dbCollector->getQueryCount();
            $queries = $dbCollector->getQueries();

            echo sprintf(
                "\n📊 GET /api/important_articles:\n" .
                "   ├─ Total queries: %d\n" .
                "   ├─ Query time: %.2fms\n" .
                "   └─ Threshold: %d queries\n",
                $queryCount,
                $dbCollector->getTime() * 1000,
                self::MAX_QUERIES_IMPORTANT
            );

            if ($queryCount > 0 && !empty($queries)) {
                echo "   \n   Queries executed:\n";
                foreach ($queries as $i => $query) {
                    if (isset($query['sql'])) {
                        $executionTime = $query['executionMS'] ?? 0;
                        echo sprintf("   %d. %s [%.2fms]\n", $i + 1, $this->simplifyQuery($query['sql']), $executionTime);
                    }
                }
                echo "\n";
            }

            $this->assertLessThanOrEqual(
                self::MAX_QUERIES_IMPORTANT,
                $queryCount,
                sprintf(
                    "Too many queries for important articles! Expected <= %d, got %d.\n" .
                    "This endpoint loads articles with images and should use eager loading.",
                    self::MAX_QUERIES_IMPORTANT,
                    $queryCount
                )
            );

            $this->detectN1Queries($queries);
        } else {
            $this->markTestSkipped('Database profiler not available');
        }
    }

    /**
     * Test for N+1 query pattern detection.
     *
     * @param array<array<string, mixed>> $queries Query data from profiler
     */
    private function detectN1Queries(array $queries): void
    {
        // Group queries by normalized SQL
        $queryGroups = [];

        foreach ($queries as $query) {
            if (!isset($query['sql'])) {
                continue;
            }
            $normalizedSql = $this->normalizeQuery($query['sql']);
            if (!isset($queryGroups[$normalizedSql])) {
                $queryGroups[$normalizedSql] = 0;
            }
            $queryGroups[$normalizedSql]++;
        }

        // Check for queries executed more than 3 times (potential N+1)
        $n1Patterns = [];
        foreach ($queryGroups as $sql => $count) {
            if ($count > 3) {
                $n1Patterns[] = [
                    'sql' => $sql,
                    'count' => $count,
                ];
            }
        }

        if (!empty($n1Patterns)) {
            echo "\n⚠️  Potential N+1 Query Patterns Detected:\n";
            foreach ($n1Patterns as $pattern) {
                echo sprintf(
                    "   - Query executed %d times: %s\n",
                    $pattern['count'],
                    $this->simplifyQuery($pattern['sql'])
                );
            }
            echo "\n";

            $this->addWarning(
                sprintf(
                    "Detected %d potential N+1 query pattern(s). " .
                    "Review eager loading configuration.",
                    count($n1Patterns)
                )
            );
        }
    }

    /**
     * Normalize SQL query for comparison (remove parameter values).
     *
     * @param string $sql SQL query
     * @return string Normalized SQL
     */
    private function normalizeQuery(string $sql): string
    {
        // Remove parameter placeholders and values
        $sql = preg_replace('/\$\d+/', '?', $sql);
        $sql = preg_replace('/\?\d+/', '?', $sql);
        $sql = preg_replace('/= \?/', '= ?', $sql);
        $sql = preg_replace('/IN \([^\)]+\)/', 'IN (?)', $sql);

        // Remove extra whitespace
        $sql = preg_replace('/\s+/', ' ', $sql);

        return trim($sql);
    }

    /**
     * Simplify SQL query for display (truncate and clean).
     *
     * @param string $sql SQL query
     * @return string Simplified SQL
     */
    private function simplifyQuery(string $sql): string
    {
        // Remove extra whitespace
        $sql = preg_replace('/\s+/', ' ', $sql);
        $sql = trim($sql);

        // Truncate long queries
        if (strlen($sql) > 120) {
            $sql = substr($sql, 0, 120) . '...';
        }

        return $sql;
    }
}
