<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\DBAL\Connection;
use Exception;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HealthCheckService
{
    private const TIMEOUT = 5; // 5 seconds timeout for external services

    public function __construct(
        private readonly Connection $connection,
        private readonly CacheItemPoolInterface $cache,
        private readonly HttpClientInterface $httpClient,
        private readonly string $elasticsearchUrl,
        private readonly string $elasticsearchPassword
    ) {
    }

    /**
     * Check all services and return aggregate health status.
     */
    public function checkAll(): array
    {
        $startTime = microtime(true);
        $checks = [];

        // Run all health checks
        $checks['database'] = $this->checkDatabase();
        $checks['redis'] = $this->checkRedis();
        $checks['elasticsearch'] = $this->checkElasticsearch();

        // Calculate overall status
        $overallStatus = 'healthy';
        foreach ($checks as $check) {
            if ('unhealthy' === $check['status']) {
                $overallStatus = 'unhealthy';
                break;
            }
        }

        $duration = (microtime(true) - $startTime) * 1000;

        return [
            'status' => $overallStatus,
            'timestamp' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'duration_ms' => round($duration, 2),
            'checks' => $checks,
        ];
    }

    /**
     * Check PostgreSQL database connection.
     */
    public function checkDatabase(): array
    {
        $startTime = microtime(true);

        try {
            // Simple SELECT 1 query to test connection
            $this->connection->executeQuery('SELECT 1');

            $duration = (microtime(true) - $startTime) * 1000;

            return [
                'status' => 'healthy',
                'duration_ms' => round($duration, 2),
                'message' => 'PostgreSQL connection is operational',
            ];
        } catch (Exception $e) {
            $duration = (microtime(true) - $startTime) * 1000;

            return [
                'status' => 'unhealthy',
                'duration_ms' => round($duration, 2),
                'message' => 'Database connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check Redis cache connection via Symfony cache pool.
     */
    public function checkRedis(): array
    {
        $startTime = microtime(true);

        try {
            // Test write and read from cache
            $testKey = 'health_check_test_'.time();
            $testValue = 'test_value_'.uniqid();

            $cacheItem = $this->cache->getItem($testKey);
            $cacheItem->set($testValue);
            $cacheItem->expiresAfter(10); // Expire in 10 seconds
            $this->cache->save($cacheItem);

            // Try to read back
            $retrievedItem = $this->cache->getItem($testKey);
            $retrievedValue = $retrievedItem->get();

            // Clean up
            $this->cache->deleteItem($testKey);

            if ($retrievedValue !== $testValue) {
                throw new Exception('Redis read/write verification failed');
            }

            $duration = (microtime(true) - $startTime) * 1000;

            return [
                'status' => 'healthy',
                'duration_ms' => round($duration, 2),
                'message' => 'Redis cache is operational',
            ];
        } catch (Exception $e) {
            $duration = (microtime(true) - $startTime) * 1000;

            return [
                'status' => 'unhealthy',
                'duration_ms' => round($duration, 2),
                'message' => 'Redis cache connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Check Elasticsearch cluster health via HTTP client.
     */
    public function checkElasticsearch(): array
    {
        $startTime = microtime(true);

        try {
            // Make HTTP request to Elasticsearch cluster health endpoint
            $response = $this->httpClient->request('GET', $this->elasticsearchUrl.'/_cluster/health', [
                'timeout' => self::TIMEOUT,
                'auth_basic' => ['elastic', $this->elasticsearchPassword],
                'verify_peer' => false, // Self-signed cert in development
                'verify_host' => false,
            ]);

            $statusCode = $response->getStatusCode();

            if (200 !== $statusCode) {
                throw new Exception('Elasticsearch returned status code '.$statusCode);
            }

            $data = $response->toArray();
            $clusterStatus = $data['status'] ?? 'unknown';

            $duration = (microtime(true) - $startTime) * 1000;

            // Consider yellow as healthy (single-node cluster in dev)
            $isHealthy = in_array($clusterStatus, ['green', 'yellow']);

            return [
                'status' => $isHealthy ? 'healthy' : 'unhealthy',
                'duration_ms' => round($duration, 2),
                'cluster_status' => $clusterStatus,
                'message' => "Elasticsearch cluster is {$clusterStatus}",
            ];
        } catch (Exception $e) {
            $duration = (microtime(true) - $startTime) * 1000;

            return [
                'status' => 'unhealthy',
                'duration_ms' => round($duration, 2),
                'message' => 'Elasticsearch connection failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Kubernetes liveness probe - checks if application is alive.
     * Returns simple boolean.
     */
    public function checkLiveness(): bool
    {
        // Application is alive if PHP process is running
        // No external dependencies checked
        return true;
    }

    /**
     * Kubernetes readiness probe - checks if application is ready to serve traffic.
     * Returns true only if critical services (database) are available.
     */
    public function checkReadiness(): bool
    {
        // Application is ready if database is accessible
        $dbCheck = $this->checkDatabase();

        return 'healthy' === $dbCheck['status'];
    }
}
