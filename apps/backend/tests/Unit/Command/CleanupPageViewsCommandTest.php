<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\CleanupPageViewsCommand;
use Doctrine\DBAL\Connection;
use Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class CleanupPageViewsCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $conn = $this->createStub(Connection::class);
        $command = new CleanupPageViewsCommand($conn);
        $this->assertSame('app:cleanup-page-views', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $conn = $this->createStub(Connection::class);
        $command = new CleanupPageViewsCommand($conn);
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandHasOptions(): void
    {
        $conn = $this->createStub(Connection::class);
        $command = new CleanupPageViewsCommand($conn);
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
        $this->assertTrue($command->getDefinition()->hasOption('anonymize-days'));
        $this->assertTrue($command->getDefinition()->hasOption('delete-days'));
    }

    public function testExecuteDryRunDoesNotModifyDb(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAssociative')->willReturn([
            'total' => 1000,
            'older_than_7_days' => 200,
            'older_than_90_days' => 50,
            'to_anonymize' => 180,
        ]);
        // Must never execute actual write statements in dry-run
        $conn->expects($this->never())->method('executeStatement');

        $command = new CleanupPageViewsCommand($conn);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('DRY RUN', $tester->getDisplay());
        $this->assertStringContainsString('1,000', $tester->getDisplay());
    }

    public function testExecuteRunsCleanup(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAssociative')->willReturn([
            'total' => 500,
            'older_than_7_days' => 100,
            'older_than_90_days' => 20,
            'to_anonymize' => 80,
        ]);
        $conn->method('executeStatement')->willReturn(0);

        $command = new CleanupPageViewsCommand($conn);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('Cleanup complete', $tester->getDisplay());
    }

    public function testExecuteHandlesException(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAssociative')->willThrowException(new Exception('DB error'));

        $command = new CleanupPageViewsCommand($conn);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('DB error', $tester->getDisplay());
    }

    public function testExecuteWithCustomDays(): void
    {
        $conn = $this->createMock(Connection::class);
        $conn->method('fetchAssociative')->willReturn([
            'total' => 100,
            'older_than_7_days' => 30,
            'older_than_90_days' => 10,
            'to_anonymize' => 25,
        ]);
        $conn->method('executeStatement')->willReturn(0);

        $command = new CleanupPageViewsCommand($conn);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--anonymize-days' => '14', '--delete-days' => '180']);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('14', $output);
        $this->assertStringContainsString('180', $output);
    }
}
