<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\ElasticsearchCreateIndexCommand;
use App\Command\ElasticsearchIndexArticlesCommand;
use App\Service\Elasticsearch\ElasticDocumentService;
use App\Service\Elasticsearch\ElasticIndexManager;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ElasticsearchCommandsTest extends TestCase
{
    // ── ElasticsearchCreateIndexCommand ─────────────────────────────────────

    public function testCreateIndexCommandName(): void
    {
        $service = $this->createStub(ElasticIndexManager::class);
        $command = new ElasticsearchCreateIndexCommand($service);
        $this->assertSame('app:elasticsearch:create-index', $command->getName());
    }

    public function testCreateIndexCommandHasLocaleOption(): void
    {
        $service = $this->createStub(ElasticIndexManager::class);
        $command = new ElasticsearchCreateIndexCommand($service);
        $this->assertTrue($command->getDefinition()->hasOption('locale'));
    }

    public function testCreateIndexWhenElasticsearchDisabled(): void
    {
        $service = $this->createMock(ElasticIndexManager::class);
        $service->method('isEnabled')->willReturn(false);

        $command = new ElasticsearchCreateIndexCommand($service);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('disabled', $tester->getDisplay());
    }

    public function testCreateIndexForAllLocales(): void
    {
        $service = $this->createMock(ElasticIndexManager::class);
        $service->method('isEnabled')->willReturn(true);
        $service->expects($this->once())->method('createAllIndices');
        $service->method('getClusterHealth')->willReturn(null);

        $command = new ElasticsearchCreateIndexCommand($service);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('All indices created', $tester->getDisplay());
    }

    public function testCreateIndexForSpecificLocale(): void
    {
        $service = $this->createMock(ElasticIndexManager::class);
        $service->method('isEnabled')->willReturn(true);
        $service->expects($this->once())->method('createIndex')->with('ro');
        $service->method('getClusterHealth')->willReturn(null);

        $command = new ElasticsearchCreateIndexCommand($service);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--locale' => 'ro']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('ro', $tester->getDisplay());
    }

    public function testCreateIndexHandlesException(): void
    {
        $service = $this->createMock(ElasticIndexManager::class);
        $service->method('isEnabled')->willReturn(true);
        $service->method('createAllIndices')->willThrowException(new Exception('ES error'));

        $command = new ElasticsearchCreateIndexCommand($service);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('ES error', $tester->getDisplay());
    }

    // ── ElasticsearchIndexArticlesCommand ─────────────────────────────────

    public function testIndexArticlesCommandName(): void
    {
        $service = $this->createStub(ElasticDocumentService::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $command = new ElasticsearchIndexArticlesCommand($service, $em);
        $this->assertSame('app:elasticsearch:index-articles', $command->getName());
    }

    public function testIndexArticlesCommandHasOptions(): void
    {
        $service = $this->createStub(ElasticDocumentService::class);
        $em = $this->createStub(EntityManagerInterface::class);
        $command = new ElasticsearchIndexArticlesCommand($service, $em);
        $this->assertTrue($command->getDefinition()->hasOption('locale'));
        $this->assertTrue($command->getDefinition()->hasOption('status'));
        $this->assertTrue($command->getDefinition()->hasOption('include-archived'));
        $this->assertTrue($command->getDefinition()->hasOption('batch-size'));
    }

    public function testIndexArticlesWhenElasticsearchDisabled(): void
    {
        $service = $this->createMock(ElasticDocumentService::class);
        $service->method('isEnabled')->willReturn(false);
        $em = $this->createStub(EntityManagerInterface::class);

        $command = new ElasticsearchIndexArticlesCommand($service, $em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('disabled', $tester->getDisplay());
    }

    public function testIndexArticlesHandlesException(): void
    {
        $service = $this->createMock(ElasticDocumentService::class);
        $service->method('isEnabled')->willReturn(true);

        $query = $this->createMock(\Doctrine\ORM\Query::class);
        $query->method('getResult')->willThrowException(new Exception('Query error'));

        $qb = $this->createMock(\Doctrine\ORM\QueryBuilder::class);
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('addSelect')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repo = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $command = new ElasticsearchIndexArticlesCommand($service, $em);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
    }
}
