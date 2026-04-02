<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;

/**
 * Functional tests for HealthController.
 *
 * Endpoints tested:
 * - GET /api/health          (full health check)
 * - GET /api/health/database (database check)
 * - GET /api/health/redis    (redis check)
 * - GET /api/health/elasticsearch (elasticsearch check)
 * - GET /api/health/live     (liveness probe)
 * - GET /api/health/ready    (readiness probe)
 *
 * All health endpoints are PUBLIC (security: false in firewall).
 */
class HealthControllerTest extends ApiTestCase
{
    // =============================================
    // GET /api/health - Full health check
    // =============================================

    public function testHealthCheckAllReturnsJson(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health');

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [200, 503]);

        $data = json_decode($response->getContent(), true);
        $this->assertNotNull($data);
        $this->assertArrayHasKey('status', $data);
        $this->assertContains($data['status'], ['healthy', 'degraded', 'unhealthy']);
    }

    public function testHealthCheckAllContainsExpectedStructure(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('status', $data);
    }

    public function testHealthCheckAllDoesNotRequireAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health');

        // Should NOT return 401 - health endpoints are public
        $this->assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    // =============================================
    // GET /api/health/database - Database check
    // =============================================

    public function testDatabaseHealthCheckReturnsJson(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health/database');

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [200, 503]);

        $data = json_decode($response->getContent(), true);
        $this->assertNotNull($data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('duration_ms', $data);
        $this->assertArrayHasKey('check', $data);
    }

    public function testDatabaseHealthCheckIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health/database');

        $this->assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    // =============================================
    // GET /api/health/redis - Redis check
    // =============================================

    public function testRedisHealthCheckReturnsJson(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health/redis');

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [200, 503]);

        $data = json_decode($response->getContent(), true);
        $this->assertNotNull($data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('duration_ms', $data);
    }

    // =============================================
    // GET /api/health/elasticsearch - Elasticsearch check
    // =============================================

    public function testElasticsearchHealthCheckReturnsJson(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health/elasticsearch');

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [200, 503]);

        $data = json_decode($response->getContent(), true);
        $this->assertNotNull($data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('duration_ms', $data);
    }

    // =============================================
    // GET /api/health/live - Liveness probe
    // =============================================

    public function testLivenessProbeReturnsJson(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health/live');

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [200, 503]);

        $data = json_decode($response->getContent(), true);
        $this->assertNotNull($data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertContains($data['status'], ['alive', 'dead']);
    }

    public function testLivenessProbeIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health/live');

        $this->assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    // =============================================
    // GET /api/health/ready - Readiness probe
    // =============================================

    public function testReadinessProbeReturnsJson(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health/ready');

        $response = $client->getResponse();
        $this->assertContains($response->getStatusCode(), [200, 503]);

        $data = json_decode($response->getContent(), true);
        $this->assertNotNull($data);
        $this->assertArrayHasKey('status', $data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertContains($data['status'], ['ready', 'not_ready']);
    }

    public function testReadinessProbeIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/health/ready');

        $this->assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    // =============================================
    // HTTP method tests
    // =============================================

    public function testHealthCheckRejectsPostMethod(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/health');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testHealthCheckRejectsPutMethod(): void
    {
        $client = static::createClient();
        $client->request('PUT', '/api/health');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testHealthCheckRejectsDeleteMethod(): void
    {
        $client = static::createClient();
        $client->request('DELETE', '/api/health');

        $this->assertResponseStatusCodeSame(405);
    }
}
