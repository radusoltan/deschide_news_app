<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial;

use App\Dto\Editorial\EntityExtractionResult;
use App\Entity\Article;
use App\Service\Editorial\ConnectionDetectionService;
use App\Service\Search\ElasticsearchIndexManager;
use App\Service\Search\SearchService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ConnectionDetectionServiceTest extends TestCase
{
    private function createServiceWithDisabledSearch(): ConnectionDetectionService
    {
        // ElasticsearchIndexManager with empty host = disabled, SearchService.search() returns empty
        $indexManager = new ElasticsearchIndexManager(elasticsearchHost: '');
        $searchService = new SearchService(indexManager: $indexManager, logger: new NullLogger());

        return new ConnectionDetectionService(
            searchService: $searchService,
            logger: new NullLogger(),
        );
    }

    public function testDetectNewConnectionsReturnsEmptyForNoEntities(): void
    {
        $service = $this->createServiceWithDisabledSearch();

        $article = new Article();
        $entities = new EntityExtractionResult();

        $connections = $service->detectNewConnections($article, $entities);

        self::assertSame([], $connections);
    }

    public function testDetectNewConnectionsReturnsEmptyWhenSearchDisabled(): void
    {
        $service = $this->createServiceWithDisabledSearch();

        $article = new Article();
        $ref = new \ReflectionProperty(Article::class, 'id');
        $ref->setValue($article, 100);

        $entities = new EntityExtractionResult(
            persons: [['name' => 'Ion Popescu']],
            institutions: [['name' => 'Ministerul Economiei']],
        );

        // With ES disabled, search returns empty — so no connections
        $connections = $service->detectNewConnections($article, $entities);

        self::assertSame([], $connections);
    }

    public function testSaveConnectionAlertsWritesFile(): void
    {
        $tmpDir = sys_get_temp_dir() . '/vault-conn-test-' . uniqid();

        $service = $this->createServiceWithDisabledSearch();

        $article = new Article();
        $article->setTitle('Test article');

        $connections = [[
            'entity1' => 'Ion Popescu',
            'entity2' => 'Ministerul Economiei',
            'type' => 'person ↔ institution',
            'sourceArticleId' => 100,
            'previousArticles' => [['id' => 50, 'title' => 'Previous article']],
            'relevance' => 'HIGH',
        ]];

        $count = $service->saveConnectionAlerts($connections, $article, $tmpDir);

        self::assertSame(1, $count);
        self::assertFileExists($tmpDir . '/alerts/connections.md');

        $content = file_get_contents($tmpDir . '/alerts/connections.md');
        self::assertStringContainsString('Ion Popescu', $content);
        self::assertStringContainsString('Ministerul Economiei', $content);
        self::assertStringContainsString('HIGH', $content);
        self::assertStringContainsString('# Alerte Conexiuni', $content);

        $this->removeDir($tmpDir);
    }

    public function testSaveConnectionAlertsReturnsZeroForEmptyConnections(): void
    {
        $service = $this->createServiceWithDisabledSearch();

        $article = new Article();
        $count = $service->saveConnectionAlerts([], $article, '/tmp');

        self::assertSame(0, $count);
    }

    public function testSaveConnectionAlertsReturnsZeroForEmptyVaultPath(): void
    {
        $service = $this->createServiceWithDisabledSearch();

        $article = new Article();
        $connections = [[
            'entity1' => 'A',
            'entity2' => 'B',
            'type' => 'test',
            'sourceArticleId' => 1,
            'previousArticles' => [],
            'relevance' => 'LOW',
        ]];

        $count = $service->saveConnectionAlerts($connections, $article, '');

        self::assertSame(0, $count);
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
