<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Aggregator;

use App\Service\Aggregator\ElasticsearchSimilarityService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ElasticsearchSimilarityService::class)]
class ElasticsearchSimilarityServiceTest extends TestCase
{
    public function testDisabledWhenHostEmpty(): void
    {
        $service = new ElasticsearchSimilarityService('');
        self::assertFalse($service->isEnabled());
    }

    public function testFindSimilarReturnsEmptyWhenDisabled(): void
    {
        $service = new ElasticsearchSimilarityService('');
        $results = $service->findSimilar('Test title', 'Test content');

        self::assertSame([], $results);
    }

    public function testEnabledWhenHostProvided(): void
    {
        // This won't actually connect — just tests initialization logic.
        // Real ES tests would be integration tests.
        $service = new ElasticsearchSimilarityService('https://localhost:9200');
        self::assertTrue($service->isEnabled());
    }
}
