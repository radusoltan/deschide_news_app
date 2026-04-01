<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Tests\Functional\ApiTestCase;

/**
 * Functional tests for MetricsController.
 *
 * Endpoints tested:
 * - GET /metrics (Prometheus metrics)
 *
 * The /metrics endpoint is PUBLIC_ACCESS per security.yaml.
 */
class MetricsControllerTest extends ApiTestCase
{
    // =============================================
    // GET /metrics
    // =============================================

    public function testMetricsEndpointIsAccessible(): void
    {
        $client = static::createClient();
        $client->request('GET', '/metrics');

        $this->assertResponseIsSuccessful();
    }

    public function testMetricsEndpointReturnsPrometheusFormat(): void
    {
        $client = static::createClient();
        $client->request('GET', '/metrics');

        $this->assertResponseIsSuccessful();

        $response = $client->getResponse();
        $contentType = $response->headers->get('Content-Type');
        $this->assertStringContainsString('text/plain', $contentType);

        // Prometheus format uses text/plain with version parameter
        $content = $response->getContent();
        $this->assertNotEmpty($content);
    }

    public function testMetricsEndpointIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/metrics');

        // Should NOT return 401
        $this->assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    public function testMetricsEndpointRejectsPostMethod(): void
    {
        $client = static::createClient();
        $client->request('POST', '/metrics');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testMetricsEndpointRejectsPutMethod(): void
    {
        $client = static::createClient();
        $client->request('PUT', '/metrics');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testMetricsEndpointRejectsDeleteMethod(): void
    {
        $client = static::createClient();
        $client->request('DELETE', '/metrics');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testMetricsContentContainsExpectedFormat(): void
    {
        $client = static::createClient();
        $client->request('GET', '/metrics');

        $this->assertResponseIsSuccessful();

        $content = $client->getResponse()->getContent();
        // Prometheus metrics format has lines like:
        // # HELP metric_name Description
        // # TYPE metric_name type
        // metric_name{label="value"} 123
        // The content should be a non-empty string
        $this->assertIsString($content);
    }
}
