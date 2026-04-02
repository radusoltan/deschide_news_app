<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\CleanupNotificationsCommand;
use App\Repository\AdminNotificationRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class CleanupNotificationsCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $repo = $this->createStub(AdminNotificationRepository::class);
        $command = new CleanupNotificationsCommand($repo, 30);
        $this->assertSame('app:notifications:cleanup', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $repo = $this->createStub(AdminNotificationRepository::class);
        $command = new CleanupNotificationsCommand($repo, 30);
        $this->assertNotEmpty($command->getDescription());
    }

    public function testExecuteDeletesOldNotifications(): void
    {
        $repo = $this->createMock(AdminNotificationRepository::class);
        $repo->method('deleteOlderThan')->willReturn(7);

        $command = new CleanupNotificationsCommand($repo, 30);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('7', $tester->getDisplay());
        $this->assertStringContainsString('30', $tester->getDisplay());
    }

    public function testExecuteWithZeroDeletions(): void
    {
        $repo = $this->createMock(AdminNotificationRepository::class);
        $repo->method('deleteOlderThan')->willReturn(0);

        $command = new CleanupNotificationsCommand($repo, 14);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('0', $tester->getDisplay());
    }

    public function testRetentionDaysUsedInCutoff(): void
    {
        $repo = $this->createMock(AdminNotificationRepository::class);

        $capturedDate = null;
        $repo->method('deleteOlderThan')
            ->willReturnCallback(function (\DateTimeImmutable $cutoff) use (&$capturedDate): int {
                $capturedDate = $cutoff;
                return 0;
            });

        $command = new CleanupNotificationsCommand($repo, 7);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertNotNull($capturedDate);
        // The cutoff should be approximately 7 days ago
        $diff = (new \DateTimeImmutable())->diff($capturedDate);
        $this->assertEqualsWithDelta(7, $diff->days, 1);
    }
}
