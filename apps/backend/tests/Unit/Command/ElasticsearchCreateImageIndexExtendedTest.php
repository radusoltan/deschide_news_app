<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\ElasticsearchCreateImageIndexCommand;
use App\Service\ImageElasticService;
use Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ElasticsearchCreateImageIndexExtendedTest extends TestCase
{
    public function testCommandName(): void
    {
        $service = $this->createStub(ImageElasticService::class);
        $command = new ElasticsearchCreateImageIndexCommand($service);
        $this->assertSame('app:elasticsearch:create-image-index', $command->getName());
    }

    public function testCommandDescription(): void
    {
        $service = $this->createStub(ImageElasticService::class);
        $command = new ElasticsearchCreateImageIndexCommand($service);
        $this->assertNotEmpty($command->getDescription());
    }

    public function testFailsWhenElasticsearchDisabled(): void
    {
        $service = $this->createMock(ImageElasticService::class);
        $service->method('isEnabled')->willReturn(false);

        $command = new ElasticsearchCreateImageIndexCommand($service);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('not enabled', $tester->getDisplay());
    }

    public function testSucceedsWhenEnabled(): void
    {
        $service = $this->createMock(ImageElasticService::class);
        $service->method('isEnabled')->willReturn(true);
        $service->expects($this->once())->method('createIndex');
        $service->method('getHealth')->willReturn([
            'status' => 'green',
            'cluster_name' => 'deschide',
            'number_of_nodes' => 1,
        ]);

        $command = new ElasticsearchCreateImageIndexCommand($service);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('created successfully', $output);
        $this->assertStringContainsString('green', $output);
    }

    public function testFailsWhenCreateIndexThrows(): void
    {
        $service = $this->createMock(ImageElasticService::class);
        $service->method('isEnabled')->willReturn(true);
        $service->method('createIndex')->willThrowException(new Exception('Index error'));

        $command = new ElasticsearchCreateImageIndexCommand($service);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Index error', $tester->getDisplay());
    }

    public function testSucceedsAndDisplaysClusterHealthProperties(): void
    {
        $service = $this->createMock(ImageElasticService::class);
        $service->method('isEnabled')->willReturn(true);
        $service->method('getHealth')->willReturn([
            'status' => 'yellow',
            'cluster_name' => 'test-cluster',
            'number_of_nodes' => 3,
        ]);

        $command = new ElasticsearchCreateImageIndexCommand($service);
        $app = new Application();
        $app->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('yellow', $output);
        $this->assertStringContainsString('test-cluster', $output);
        $this->assertStringContainsString('3', $output);
    }
}
