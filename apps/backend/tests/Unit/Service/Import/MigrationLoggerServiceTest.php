<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Import;

use App\Service\Import\MigrationLoggerService;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class MigrationLoggerServiceTest extends TestCase
{
    private Connection $connection;
    private MigrationLoggerService $service;

    protected function setUp(): void
    {
        $this->connection = $this->createStub(Connection::class);
        $this->service = new MigrationLoggerService($this->connection);
    }

    // --- logSuccess ---

    public function testLogSuccessInsertsRecord(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->connection->expects($this->once())
            ->method('insert')
            ->with(
                'newscoop_migration_log',
                $this->callback(function (array $data) {
                    return $data['entity_type'] === 'article'
                        && $data['newscoop_id'] === '42'
                        && $data['deschide_id'] === 100
                        && $data['status'] === 'success'
                        && $data['error_message'] === null;
                })
            );

        $service = new MigrationLoggerService($this->connection);
        $service->logSuccess('article', 42, 100);
    }

    public function testLogSuccessWithAdditionalData(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->connection->expects($this->once())
            ->method('insert')
            ->with(
                'newscoop_migration_log',
                $this->callback(function (array $data) {
                    $additional = json_decode($data['additional_data'], true);
                    return $additional['locale'] === 'ro';
                })
            );

        $service = new MigrationLoggerService($this->connection);
        $service->logSuccess('article', 42, 100, ['locale' => 'ro']);
    }

    public function testLogSuccessWithStringNewscoopId(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->connection->expects($this->once())
            ->method('insert')
            ->with(
                'newscoop_migration_log',
                $this->callback(function (array $data) {
                    return $data['newscoop_id'] === 'slug-based-id';
                })
            );

        $service = new MigrationLoggerService($this->connection);
        $service->logSuccess('category', 'slug-based-id', 5);
    }

    // --- logError ---

    public function testLogErrorInsertsErrorRecord(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->connection->expects($this->once())
            ->method('insert')
            ->with(
                'newscoop_migration_log',
                $this->callback(function (array $data) {
                    return $data['entity_type'] === 'image'
                        && $data['status'] === 'error'
                        && $data['deschide_id'] === null
                        && $data['error_message'] === 'File not found';
                })
            );

        $service = new MigrationLoggerService($this->connection);
        $service->logError('image', 999, 'File not found');
    }

    public function testLogErrorTruncatesLongErrorMessage(): void
    {
        $longMessage = str_repeat('x', 6000);

        $this->connection = $this->createMock(Connection::class);
        $this->connection->expects($this->once())
            ->method('insert')
            ->with(
                'newscoop_migration_log',
                $this->callback(function (array $data) {
                    return strlen($data['error_message']) <= 5000;
                })
            );

        $service = new MigrationLoggerService($this->connection);
        $service->logError('article', 1, $longMessage);
    }

    public function testLogErrorWithAdditionalData(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->connection->expects($this->once())
            ->method('insert')
            ->with(
                'newscoop_migration_log',
                $this->callback(function (array $data) {
                    $additional = json_decode($data['additional_data'], true);
                    return $additional['step'] === 'translation';
                })
            );

        $service = new MigrationLoggerService($this->connection);
        $service->logError('article', 42, 'Failed', ['step' => 'translation']);
    }

    // --- getMapping ---

    public function testGetMappingReturnsIdWhenExists(): void
    {
        $this->connection->method('fetchOne')->willReturn('100');

        $result = $this->service->getMapping('article', 42);

        $this->assertSame(100, $result);
    }

    public function testGetMappingReturnsNullWhenNotFound(): void
    {
        $this->connection->method('fetchOne')->willReturn(false);

        $result = $this->service->getMapping('article', 999);

        $this->assertNull($result);
    }

    // --- getAllMappings ---

    public function testGetAllMappingsReturnsArray(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([
            ['newscoop_id' => '1', 'deschide_id' => 10],
            ['newscoop_id' => '2', 'deschide_id' => 20],
            ['newscoop_id' => '3', 'deschide_id' => 30],
        ]);

        $result = $this->service->getAllMappings('article');

        $this->assertSame(['1' => 10, '2' => 20, '3' => 30], $result);
    }

    public function testGetAllMappingsReturnsEmptyArrayWhenNone(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $result = $this->service->getAllMappings('article');

        $this->assertSame([], $result);
    }

    // --- getMappings ---

    public function testGetMappingsWithPagination(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([
            ['newscoop_id' => '5', 'deschide_id' => 50],
        ]);

        $result = $this->service->getMappings('article', 'success', 10, 20);

        $this->assertSame(['5' => 50], $result);
    }

    public function testGetMappingsWithoutLimit(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([
            ['newscoop_id' => '1', 'deschide_id' => 10],
        ]);

        $result = $this->service->getMappings('category');

        $this->assertSame(['1' => 10], $result);
    }

    // --- isImported ---

    public function testIsImportedReturnsTrueWhenImported(): void
    {
        $this->connection->method('fetchOne')->willReturn('1');

        $result = $this->service->isImported('article', 42);

        $this->assertTrue($result);
    }

    public function testIsImportedReturnsFalseWhenNotImported(): void
    {
        $this->connection->method('fetchOne')->willReturn('0');

        $result = $this->service->isImported('article', 999);

        $this->assertFalse($result);
    }

    // --- getStats ---

    public function testGetStatsForSpecificEntityType(): void
    {
        $this->connection->method('fetchOne')
            ->willReturnOnConsecutiveCalls('150', '10');

        $result = $this->service->getStats('article');

        $this->assertSame(150, $result['success']);
        $this->assertSame(10, $result['error']);
        $this->assertSame(160, $result['total']);
    }

    public function testGetStatsForAllEntityTypes(): void
    {
        $this->connection->method('fetchOne')
            ->willReturnOnConsecutiveCalls('500', '25');

        $result = $this->service->getStats();

        $this->assertSame(500, $result['success']);
        $this->assertSame(25, $result['error']);
        $this->assertSame(525, $result['total']);
    }

    // --- getStatsByEntityType ---

    public function testGetStatsByEntityTypeReturnsGroupedStats(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([
            ['entity_type' => 'article', 'status' => 'success', 'count' => 100],
            ['entity_type' => 'article', 'status' => 'error', 'count' => 5],
            ['entity_type' => 'category', 'status' => 'success', 'count' => 20],
        ]);

        $result = $this->service->getStatsByEntityType();

        $this->assertArrayHasKey('article', $result);
        $this->assertSame(100, $result['article']['success']);
        $this->assertSame(5, $result['article']['error']);
        $this->assertSame(105, $result['article']['total']);
        $this->assertArrayHasKey('category', $result);
        $this->assertSame(20, $result['category']['success']);
        $this->assertSame(0, $result['category']['error']);
        $this->assertSame(20, $result['category']['total']);
    }

    // --- getRecentErrors ---

    public function testGetRecentErrorsReturnsErrors(): void
    {
        $errors = [
            ['entity_type' => 'article', 'newscoop_id' => '42', 'error_message' => 'Failed', 'created_at' => '2026-03-26 10:00:00'],
        ];
        $this->connection->method('fetchAllAssociative')->willReturn($errors);

        $result = $this->service->getRecentErrors(50);

        $this->assertCount(1, $result);
        $this->assertSame('article', $result[0]['entity_type']);
    }

    public function testGetRecentErrorsReturnsEmptyWhenNoErrors(): void
    {
        $this->connection->method('fetchAllAssociative')->willReturn([]);

        $result = $this->service->getRecentErrors();

        $this->assertSame([], $result);
    }

    // --- clearLogs ---

    public function testClearLogsDeletesByEntityType(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->connection->expects($this->once())
            ->method('delete')
            ->with('newscoop_migration_log', ['entity_type' => 'article']);

        $service = new MigrationLoggerService($this->connection);
        $service->clearLogs('article');
    }

    // --- clearAllLogs ---

    public function testClearAllLogsTruncatesTable(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->connection->expects($this->once())
            ->method('executeStatement')
            ->with('TRUNCATE TABLE newscoop_migration_log');

        $service = new MigrationLoggerService($this->connection);
        $service->clearAllLogs();
    }
}
