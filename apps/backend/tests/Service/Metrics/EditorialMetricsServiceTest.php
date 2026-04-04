<?php

declare(strict_types=1);

namespace App\Tests\Service\Metrics;

use App\Service\Metrics\EditorialMetricsService;
use App\Service\Search\ElasticsearchIndexManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class EditorialMetricsServiceTest extends TestCase
{
    private EntityManagerInterface $em;
    private ElasticsearchIndexManager $esManager;
    private EditorialMetricsService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->esManager = $this->createMock(ElasticsearchIndexManager::class);

        $this->service = new EditorialMetricsService(
            $this->em,
            $this->esManager,
            new NullLogger(),
        );
    }

    #[Test]
    public function collectMetricsReturnsAllCategories(): void
    {
        $conn = $this->createMock(Connection::class);
        $this->em->method('getConnection')->willReturn($conn);

        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([]);

        $conn->method('fetchOne')->willReturn('0');
        $conn->method('fetchAllAssociative')->willReturn([]);
        $conn->method('executeQuery')->willReturn($result);

        $this->esManager->method('isEnabled')->willReturn(false);

        $metrics = $this->service->collectMetrics();

        $this->assertArrayHasKey('period', $metrics);
        $this->assertArrayHasKey('volume', $metrics);
        $this->assertArrayHasKey('translations', $metrics);
        $this->assertArrayHasKey('quality', $metrics);
        $this->assertArrayHasKey('elasticsearch', $metrics);
        $this->assertArrayHasKey('pipeline', $metrics);
    }

    #[Test]
    public function collectMetricsWithCustomPeriod(): void
    {
        $conn = $this->createMock(Connection::class);
        $this->em->method('getConnection')->willReturn($conn);

        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([]);

        $conn->method('fetchOne')->willReturn('100');
        $conn->method('fetchAllAssociative')->willReturn([]);
        $conn->method('executeQuery')->willReturn($result);

        $this->esManager->method('isEnabled')->willReturn(false);

        $from = new \DateTimeImmutable('2026-03-01');
        $to = new \DateTimeImmutable('2026-03-31');

        $metrics = $this->service->collectMetrics($from, $to);

        $this->assertSame('2026-03-01', $metrics['period']['from']);
        $this->assertSame('2026-03-31', $metrics['period']['to']);
        $this->assertSame(30, $metrics['period']['days']);
    }

    #[Test]
    public function collectMetricsJsonOutputValid(): void
    {
        $conn = $this->createMock(Connection::class);
        $this->em->method('getConnection')->willReturn($conn);

        $result = $this->createMock(Result::class);
        $result->method('fetchAllAssociative')->willReturn([]);

        $conn->method('fetchOne')->willReturn('50');
        $conn->method('fetchAllAssociative')->willReturn([]);
        $conn->method('executeQuery')->willReturn($result);

        $this->esManager->method('isEnabled')->willReturn(false);

        $metrics = $this->service->collectMetrics();

        $json = json_encode($metrics, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE);
        $this->assertNotFalse($json);

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('volume', $decoded);
        $this->assertArrayHasKey('total', $decoded['volume']);
    }
}
