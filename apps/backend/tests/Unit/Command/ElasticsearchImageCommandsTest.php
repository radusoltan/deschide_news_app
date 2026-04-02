<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\ElasticsearchCreateImageIndexCommand;
use App\Command\ElasticsearchManageAliasCommand;
use App\Service\ElasticService;
use App\Service\ImageElasticService;
use Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ElasticsearchImageCommandsTest extends TestCase
{
    // ── ElasticsearchCreateImageIndexCommand ─────────────────────────────

    public function testCreateImageIndexCommandName(): void
    {
        $service = $this->createStub(ImageElasticService::class);
        $command = new ElasticsearchCreateImageIndexCommand($service);
        $this->assertSame('app:elasticsearch:create-image-index', $command->getName());
    }

    public function testCreateImageIndexWhenDisabledFails(): void
    {
        $service = $this->createMock(ImageElasticService::class);
        $service->method('isEnabled')->willReturn(false);

        $command = new ElasticsearchCreateImageIndexCommand($service);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('not enabled', $tester->getDisplay());
    }

    public function testCreateImageIndexSucceeds(): void
    {
        $service = $this->createMock(ImageElasticService::class);
        $service->method('isEnabled')->willReturn(true);
        $service->method('getHealth')->willReturn([
            'status' => 'green',
            'cluster_name' => 'test',
            'number_of_nodes' => 1,
        ]);

        $command = new ElasticsearchCreateImageIndexCommand($service);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('created successfully', $tester->getDisplay());
    }

    public function testCreateImageIndexHandlesException(): void
    {
        $service = $this->createMock(ImageElasticService::class);
        $service->method('isEnabled')->willReturn(true);
        $service->method('createIndex')->willThrowException(new Exception('Index error'));

        $command = new ElasticsearchCreateImageIndexCommand($service);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Index error', $tester->getDisplay());
    }

    // ── ElasticsearchManageAliasCommand ────────────────────────────────────

    public function testManageAliasCommandName(): void
    {
        $service = $this->createStub(ElasticService::class);
        $command = new ElasticsearchManageAliasCommand($service);
        $this->assertSame('app:elasticsearch:manage-alias', $command->getName());
    }

    public function testManageAliasCommandHasOptions(): void
    {
        $service = $this->createStub(ElasticService::class);
        $command = new ElasticsearchManageAliasCommand($service);
        $this->assertTrue($command->getDefinition()->hasOption('action'));
        $this->assertTrue($command->getDefinition()->hasOption('alias'));
    }

    public function testManageAliasWhenDisabled(): void
    {
        $service = $this->createMock(ElasticService::class);
        $service->method('isEnabled')->willReturn(false);

        $command = new ElasticsearchManageAliasCommand($service);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('disabled', $tester->getDisplay());
    }

    public function testManageAliasHandlesException(): void
    {
        $service = $this->createMock(ElasticService::class);
        $service->method('isEnabled')->willReturn(true);
        $service->method('getAliases')->willThrowException(new Exception('Alias error'));

        $command = new ElasticsearchManageAliasCommand($service);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--action' => 'list']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Alias error', $tester->getDisplay());
    }
}
