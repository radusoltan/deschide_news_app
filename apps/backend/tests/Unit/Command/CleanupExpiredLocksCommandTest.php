<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\CleanupExpiredLocksCommand;
use App\Repository\ArticleLockRepository;
use Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class CleanupExpiredLocksCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $repo = $this->createStub(ArticleLockRepository::class);
        $command = new CleanupExpiredLocksCommand($repo);
        $this->assertSame('app:cleanup-expired-locks', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $repo = $this->createStub(ArticleLockRepository::class);
        $command = new CleanupExpiredLocksCommand($repo);
        $this->assertNotEmpty($command->getDescription());
    }

    public function testExecuteWithDeletedLocks(): void
    {
        $repo = $this->createMock(ArticleLockRepository::class);
        $repo->method('deleteExpiredLocks')->willReturn(5);

        $command = new CleanupExpiredLocksCommand($repo);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('5', $tester->getDisplay());
    }

    public function testExecuteWithNoExpiredLocks(): void
    {
        $repo = $this->createMock(ArticleLockRepository::class);
        $repo->method('deleteExpiredLocks')->willReturn(0);

        $command = new CleanupExpiredLocksCommand($repo);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('No expired locks found', $tester->getDisplay());
    }

    public function testExecuteHandlesException(): void
    {
        $repo = $this->createMock(ArticleLockRepository::class);
        $repo->method('deleteExpiredLocks')->willThrowException(new Exception('DB error'));

        $command = new CleanupExpiredLocksCommand($repo);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('DB error', $tester->getDisplay());
    }
}
