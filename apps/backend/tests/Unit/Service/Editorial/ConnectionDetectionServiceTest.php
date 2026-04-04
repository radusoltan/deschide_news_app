<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial;

use App\Dto\Editorial\EntityExtractionResult;
use App\Entity\Article;
use App\Entity\GeneratedContent;
use App\Service\Editorial\ConnectionDetectionService;
use App\Service\Search\ElasticsearchIndexManager;
use App\Service\Search\SearchService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ConnectionDetectionServiceTest extends TestCase
{
    private function createServiceWithDisabledSearch(): ConnectionDetectionService
    {
        // ElasticsearchIndexManager with empty host = disabled, SearchService.search() returns empty
        $indexManager = new ElasticsearchIndexManager(elasticsearchHost: '');
        $searchService = new SearchService(indexManager: $indexManager, logger: new NullLogger());

        $em = $this->createMock(EntityManagerInterface::class);

        return new ConnectionDetectionService(
            searchService: $searchService,
            em: $em,
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

    public function testSaveConnectionAlertsPersistsGeneratedContent(): void
    {
        $indexManager = new ElasticsearchIndexManager(elasticsearchHost: '');
        $searchService = new SearchService(indexManager: $indexManager, logger: new NullLogger());

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')->with($this->isInstanceOf(GeneratedContent::class));
        $em->expects($this->once())->method('flush');

        $service = new ConnectionDetectionService(
            searchService: $searchService,
            em: $em,
            logger: new NullLogger(),
        );

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

        $count = $service->saveConnectionAlerts($connections, $article);

        self::assertSame(1, $count);
    }

    public function testSaveConnectionAlertsReturnsZeroForEmptyConnections(): void
    {
        $service = $this->createServiceWithDisabledSearch();

        $article = new Article();
        $count = $service->saveConnectionAlerts([], $article);

        self::assertSame(0, $count);
    }
}
