<?php

declare(strict_types=1);

namespace App\Tests\Smoke;

use PHPUnit\Framework\Attributes\Group;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Health Check Smoke Tests.
 *
 * Verifies that the API health endpoints are accessible and responding
 * within acceptable time limits.
 */
#[Group('smoke')]
class HealthCheckTest extends WebTestCase
{
    private const TIMEOUT_MS = 500;

    public function testApiRedirectsHealthEndpointReturns200(): void
    {
        $client = static::createClient();

        $startTime = microtime(true);
        $client->request('GET', '/api/redirects/health');
        $duration = (microtime(true) - $startTime) * 1000;

        $this->assertResponseIsSuccessful();
        $this->assertLessThan(
            self::TIMEOUT_MS,
            $duration,
            sprintf('Health check took %.2fms, expected less than %dms', $duration, self::TIMEOUT_MS)
        );
    }

    public function testApiRedirectsHealthReturnsJson(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/redirects/health', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        $this->assertArrayHasKey('success', $data);
        $this->assertArrayHasKey('health', $data);
        $this->assertArrayHasKey('status', $data['health']);
    }

    public function testHealthCheckResponseTimeIsConsistent(): void
    {
        $client = static::createClient();
        $durations = [];

        // Run 5 health checks and ensure they all respond quickly
        for ($i = 0; $i < 5; $i++) {
            $startTime = microtime(true);
            $client->request('GET', '/api/redirects/health');
            $durations[] = (microtime(true) - $startTime) * 1000;

            $this->assertResponseIsSuccessful();
        }

        $avgDuration = array_sum($durations) / count($durations);
        $this->assertLessThan(
            self::TIMEOUT_MS,
            $avgDuration,
            sprintf('Average health check duration %.2fms exceeds %dms threshold', $avgDuration, self::TIMEOUT_MS)
        );
    }
}
