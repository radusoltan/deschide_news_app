<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\CleanupUnusedTagsCommand;
use App\Message\CleanupUnusedTagsMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class CleanupUnusedTagsCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $bus = $this->createStub(MessageBusInterface::class);
        $command = new CleanupUnusedTagsCommand($bus);
        $this->assertSame('app:tags:cleanup', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $bus = $this->createStub(MessageBusInterface::class);
        $command = new CleanupUnusedTagsCommand($bus);
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandHasOptions(): void
    {
        $bus = $this->createStub(MessageBusInterface::class);
        $command = new CleanupUnusedTagsCommand($bus);
        $this->assertTrue($command->getDefinition()->hasOption('days'));
        $this->assertTrue($command->getDefinition()->hasOption('dry-run'));
        $this->assertTrue($command->getDefinition()->hasOption('async'));
    }

    public function testExecuteDispatchesMessage(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(CleanupUnusedTagsMessage::class))
            ->willReturn(new Envelope(new CleanupUnusedTagsMessage(30, false)));

        $command = new CleanupUnusedTagsCommand($bus);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
    }

    public function testExecuteWithDryRunOption(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->willReturn(new Envelope(new CleanupUnusedTagsMessage(30, true)));

        $command = new CleanupUnusedTagsCommand($bus);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('DRY RUN', $tester->getDisplay());
    }

    public function testExecuteWithCustomDays(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);

        $capturedMessage = null;
        $bus->method('dispatch')
            ->willReturnCallback(function ($message) use (&$capturedMessage): Envelope {
                $capturedMessage = $message;
                return new Envelope($message);
            });

        $command = new CleanupUnusedTagsCommand($bus);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--days' => '60']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertInstanceOf(CleanupUnusedTagsMessage::class, $capturedMessage);
    }

    public function testExecuteAsyncMode(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')
            ->willReturn(new Envelope(new CleanupUnusedTagsMessage(30, false)));

        $command = new CleanupUnusedTagsCommand($bus);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--async' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('message queue', $tester->getDisplay());
    }
}
