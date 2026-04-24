<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\ElasticsearchManageAliasCommand;
use App\Service\Elasticsearch\ElasticIndexManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ElasticsearchManageAliasExtendedTest extends TestCase
{
    public function testListActionReturnsSuccessWithNoAliases(): void
    {
        $service = $this->createMock(ElasticIndexManager::class);
        $service->method('isEnabled')->willReturn(true);
        $service->method('getAliases')->willReturn([]);

        $command = new ElasticsearchManageAliasCommand($service);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--action' => 'list']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('No aliases found', $tester->getDisplay());
    }

    public function testListActionWithAliases(): void
    {
        $service = $this->createMock(ElasticIndexManager::class);
        $service->method('isEnabled')->willReturn(true);
        $service->method('getAliases')->willReturn([
            'deschide_articles_ro' => [
                'aliases' => [
                    'articles_ro' => [],
                ],
            ],
        ]);

        $command = new ElasticsearchManageAliasCommand($service);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--action' => 'list']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('deschide_articles_ro', $tester->getDisplay());
    }

    public function testCreateActionMissingAlias(): void
    {
        $service = $this->createMock(ElasticIndexManager::class);
        $service->method('isEnabled')->willReturn(true);

        $command = new ElasticsearchManageAliasCommand($service);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--action' => 'create', '--index' => 'some_index']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('required', $tester->getDisplay());
    }

    public function testCreateActionMissingIndex(): void
    {
        $service = $this->createMock(ElasticIndexManager::class);
        $service->method('isEnabled')->willReturn(true);

        $command = new ElasticsearchManageAliasCommand($service);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--action' => 'create', '--alias' => 'my_alias']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('required', $tester->getDisplay());
    }

    public function testCreateActionSuccess(): void
    {
        $service = $this->createMock(ElasticIndexManager::class);
        $service->method('isEnabled')->willReturn(true);
        $service->expects($this->once())->method('createAlias')->with('my_alias', 'my_index');

        $command = new ElasticsearchManageAliasCommand($service);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--action' => 'create', '--alias' => 'my_alias', '--index' => 'my_index']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('created successfully', $tester->getDisplay());
    }

    public function testSwapActionMissingOptions(): void
    {
        $service = $this->createMock(ElasticIndexManager::class);
        $service->method('isEnabled')->willReturn(true);

        $command = new ElasticsearchManageAliasCommand($service);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--action' => 'swap', '--alias' => 'my_alias']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('required', $tester->getDisplay());
    }

    public function testReindexActionMissingSource(): void
    {
        $service = $this->createMock(ElasticIndexManager::class);
        $service->method('isEnabled')->willReturn(true);

        $command = new ElasticsearchManageAliasCommand($service);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--action' => 'reindex', '--dest' => 'new_index']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('required', $tester->getDisplay());
    }

    public function testReindexActionMissingDest(): void
    {
        $service = $this->createMock(ElasticIndexManager::class);
        $service->method('isEnabled')->willReturn(true);

        $command = new ElasticsearchManageAliasCommand($service);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--action' => 'reindex', '--source' => 'old_index']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('required', $tester->getDisplay());
    }

    public function testDefaultActionShowsUsage(): void
    {
        $service = $this->createMock(ElasticIndexManager::class);
        $service->method('isEnabled')->willReturn(true);

        $command = new ElasticsearchManageAliasCommand($service);
        $application = new Application();
        $application->addCommand($command);

        // No --action provided → showUsage
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('Alias Management', $output);
    }

    public function testSwapActionCancelledByUser(): void
    {
        $service = $this->createMock(ElasticIndexManager::class);
        $service->method('isEnabled')->willReturn(true);

        $command = new ElasticsearchManageAliasCommand($service);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        // 'n' for the confirmation prompt
        $tester->setInputs(['no']);
        $tester->execute([
            '--action' => 'swap',
            '--alias' => 'articles',
            '--old-index' => 'old_idx',
            '--new-index' => 'new_idx',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('cancelled', $tester->getDisplay());
    }

    public function testReindexActionCancelledByUser(): void
    {
        $service = $this->createMock(ElasticIndexManager::class);
        $service->method('isEnabled')->willReturn(true);

        $command = new ElasticsearchManageAliasCommand($service);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->setInputs(['no']);
        $tester->execute([
            '--action' => 'reindex',
            '--source' => 'src_index',
            '--dest' => 'dst_index',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('cancelled', $tester->getDisplay());
    }

    public function testReindexActionSucceeds(): void
    {
        $service = $this->createMock(ElasticIndexManager::class);
        $service->method('isEnabled')->willReturn(true);
        $service->method('reindex')->willReturn([
            'total' => 100,
            'created' => 100,
            'updated' => 0,
            'deleted' => 0,
            'took' => 500,
        ]);

        $command = new ElasticsearchManageAliasCommand($service);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->setInputs(['yes']);
        $tester->execute([
            '--action' => 'reindex',
            '--source' => 'src_index',
            '--dest' => 'dst_index',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Reindex completed', $tester->getDisplay());
    }
}
