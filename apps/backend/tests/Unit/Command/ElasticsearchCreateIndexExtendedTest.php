<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\ElasticsearchCreateIndexCommand;
use App\Service\Elasticsearch\ElasticIndexManager;
use Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ElasticsearchCreateIndexExtendedTest extends TestCase
{
    public function testCommandName(): void
    {
        $service = $this->createStub(ElasticIndexManager::class);
        $command = new ElasticsearchCreateIndexCommand($service);
        $this->assertSame('app:elasticsearch:create-index', $command->getName());
    }

    public function testCommandDescription(): void
    {
        $service = $this->createStub(ElasticIndexManager::class);
        $command = new ElasticsearchCreateIndexCommand($service);
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandHasLocaleOption(): void
    {
        $service = $this->createStub(ElasticIndexManager::class);
        $command = new ElasticsearchCreateIndexCommand($service);
        $this->assertTrue($command->getDefinition()->hasOption('locale'));
    }

    public function testReturnsSuccessWhenDisabled(): void
    {
        $service = $this->createMock(ElasticIndexManager::class);
        $service->method('isEnabled')->willReturn(false);

        $command = new ElasticsearchCreateIndexCommand($service);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('disabled', $tester->getDisplay());
    }

    public function testCreatesAllIndicesWhenNoLocale(): void
    {
        $service = $this->createMock(ElasticIndexManager::class);
        $service->method('isEnabled')->willReturn(true);
        $service->expects($this->once())->method('createAllIndices');
        $service->method('getClusterHealth')->willReturn(null);

        $command = new ElasticsearchCreateIndexCommand($service);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('All indices created', $tester->getDisplay());
    }

    public function testCreatesSpecificLocaleIndex(): void
    {
        $service = $this->createMock(ElasticIndexManager::class);
        $service->method('isEnabled')->willReturn(true);
        $service->expects($this->once())->method('createIndex')->with('ro');
        $service->method('getClusterHealth')->willReturn(null);

        $command = new ElasticsearchCreateIndexCommand($service);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--locale' => 'ro']);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('ro', $output);
        $this->assertStringContainsString('created successfully', $output);
    }

    public function testDisplaysClusterHealthWhenAvailable(): void
    {
        $service = $this->createMock(ElasticIndexManager::class);
        $service->method('isEnabled')->willReturn(true);
        $service->method('getClusterHealth')->willReturn([
            'cluster_name' => 'my-cluster',
            'status' => 'green',
            'number_of_nodes' => 2,
        ]);

        $command = new ElasticsearchCreateIndexCommand($service);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('my-cluster', $output);
        $this->assertStringContainsString('green', $output);
        $this->assertStringContainsString('2', $output);
    }

    public function testHandlesException(): void
    {
        $service = $this->createMock(ElasticIndexManager::class);
        $service->method('isEnabled')->willReturn(true);
        $service->method('createAllIndices')->willThrowException(new Exception('ES down'));

        $command = new ElasticsearchCreateIndexCommand($service);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('ES down', $tester->getDisplay());
    }

    public function testCreatesEnIndex(): void
    {
        $service = $this->createMock(ElasticIndexManager::class);
        $service->method('isEnabled')->willReturn(true);
        $service->expects($this->once())->method('createIndex')->with('en');
        $service->method('getClusterHealth')->willReturn(null);

        $command = new ElasticsearchCreateIndexCommand($service);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--locale' => 'en']);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testCreatesRuIndex(): void
    {
        $service = $this->createMock(ElasticIndexManager::class);
        $service->method('isEnabled')->willReturn(true);
        $service->expects($this->once())->method('createIndex')->with('ru');
        $service->method('getClusterHealth')->willReturn(null);

        $command = new ElasticsearchCreateIndexCommand($service);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--locale' => 'ru']);

        $this->assertSame(0, $tester->getStatusCode());
    }
}
