<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\CacheClearCommand;
use App\Service\PerformanceService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class CacheClearCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $perf = $this->createStub(PerformanceService::class);
        $logger = $this->createStub(LoggerInterface::class);
        $command = new CacheClearCommand($perf, $logger);
        $this->assertSame('app:cache:clear', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $perf = $this->createStub(PerformanceService::class);
        $logger = $this->createStub(LoggerInterface::class);
        $command = new CacheClearCommand($perf, $logger);
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandHasOptions(): void
    {
        $perf = $this->createStub(PerformanceService::class);
        $logger = $this->createStub(LoggerInterface::class);
        $command = new CacheClearCommand($perf, $logger);
        $this->assertTrue($command->getDefinition()->hasOption('type'));
        $this->assertTrue($command->getDefinition()->hasOption('force'));
    }

    public function testExecuteWithForceAndTypeAll(): void
    {
        $perf = $this->createMock(PerformanceService::class);
        $perf->method('deleteCachedPattern')->with('*')->willReturn(42);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('info');

        $command = new CacheClearCommand($perf, $logger);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--force' => true, '--type' => 'all']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('42', $tester->getDisplay());
    }

    public function testExecuteWithForceAndTypeArticles(): void
    {
        $perf = $this->createMock(PerformanceService::class);
        $perf->method('deleteCachedPattern')->with('api:articles:*')->willReturn(10);

        $logger = $this->createStub(LoggerInterface::class);

        $command = new CacheClearCommand($perf, $logger);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--force' => true, '--type' => 'articles']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('10', $tester->getDisplay());
    }

    public function testExecuteWithForceAndTypeCategories(): void
    {
        $perf = $this->createMock(PerformanceService::class);
        $perf->method('deleteCachedPattern')->with('api:categories:*')->willReturn(5);

        $logger = $this->createStub(LoggerInterface::class);

        $command = new CacheClearCommand($perf, $logger);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--force' => true, '--type' => 'categories']);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testExecuteWithInvalidTypeThrowsException(): void
    {
        $perf = $this->createStub(PerformanceService::class);
        $logger = $this->createStub(LoggerInterface::class);

        $command = new CacheClearCommand($perf, $logger);
        $application = new Application();
        $application->addCommand($command);

        $this->expectException(\InvalidArgumentException::class);

        $tester = new CommandTester($command);
        $tester->execute(['--force' => true, '--type' => 'invalid_type']);
    }
}
