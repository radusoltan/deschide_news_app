<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\RecalculateTagCountsCommand;
use App\Message\RecalculateTagCountsMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class RecalculateTagCountsCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $bus = $this->createStub(MessageBusInterface::class);
        $command = new RecalculateTagCountsCommand($bus);
        $this->assertSame('app:tags:recalculate-counts', $command->getName());
    }

    public function testCommandHasDescription(): void
    {
        $bus = $this->createStub(MessageBusInterface::class);
        $command = new RecalculateTagCountsCommand($bus);
        $this->assertNotEmpty($command->getDescription());
    }

    public function testCommandHasOptions(): void
    {
        $bus = $this->createStub(MessageBusInterface::class);
        $command = new RecalculateTagCountsCommand($bus);
        $this->assertTrue($command->getDefinition()->hasOption('tag-id'));
        $this->assertTrue($command->getDefinition()->hasOption('async'));
    }

    public function testExecuteDispatchesMessageForAllTags(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);

        $capturedMessage = null;
        $bus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function ($message) use (&$capturedMessage): Envelope {
                $capturedMessage = $message;
                return new Envelope($message);
            });

        $command = new RecalculateTagCountsCommand($bus);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertInstanceOf(RecalculateTagCountsMessage::class, $capturedMessage);
    }

    public function testExecuteDispatchesMessageForSpecificTag(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);

        $capturedMessage = null;
        $bus->method('dispatch')
            ->willReturnCallback(function ($message) use (&$capturedMessage): Envelope {
                $capturedMessage = $message;
                return new Envelope($message);
            });

        $command = new RecalculateTagCountsCommand($bus);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--tag-id' => '42']);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('42', $tester->getDisplay());
    }

    public function testExecuteAsyncMode(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')
            ->willReturn(new Envelope(new RecalculateTagCountsMessage(null)));

        $command = new RecalculateTagCountsCommand($bus);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute(['--async' => true]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertStringContainsString('message queue', $tester->getDisplay());
    }

    public function testExecuteSyncMode(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')
            ->willReturn(new Envelope(new RecalculateTagCountsMessage(null)));

        $command = new RecalculateTagCountsCommand($bus);
        $application = new Application();
        $application->addCommand($command);

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(0, $tester->getStatusCode());
        $output = $tester->getDisplay();
        $this->assertStringContainsString('completed', $output);
    }
}
