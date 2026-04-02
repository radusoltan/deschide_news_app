<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\TranslateEntitiesCommand;
use App\Enum\TranslatableEntityType;
use App\Message\TranslateEntityMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(TranslateEntitiesCommand::class)]
class TranslateEntitiesCommandTest extends TestCase
{
    #[Test]
    public function invalidEntityTypeShowsError(): void
    {
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $command = new TranslateEntitiesCommand($bus);
        $tester = new CommandTester($command);
        $tester->execute(['type' => 'invalid', 'ids' => '1,2']);

        $this->assertSame(1, $tester->getStatusCode());
        $this->assertStringContainsString('Invalid entity type', $tester->getDisplay());
    }

    #[Test]
    public function dispatchesCategoryMessages(): void
    {
        $dispatched = [];

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (TranslateEntityMessage $msg) use (&$dispatched) {
                $dispatched[] = $msg;

                return new Envelope($msg);
            });

        $command = new TranslateEntitiesCommand($bus);
        $tester = new CommandTester($command);
        $tester->execute([
            'type' => 'category',
            'ids' => '1,5',
            '--force' => true,
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertCount(2, $dispatched);
        $this->assertSame(TranslatableEntityType::CATEGORY, $dispatched[0]->entityType);
        $this->assertSame(1, $dispatched[0]->entityId);
        $this->assertTrue($dispatched[0]->force);
        $this->assertSame(5, $dispatched[1]->entityId);
    }

    #[Test]
    public function dispatchesAuthorMessagesWithCustomLocales(): void
    {
        $dispatched = [];

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (TranslateEntityMessage $msg) use (&$dispatched) {
                $dispatched[] = $msg;

                return new Envelope($msg);
            });

        $command = new TranslateEntitiesCommand($bus);
        $tester = new CommandTester($command);
        $tester->execute([
            'type' => 'author',
            'ids' => '42',
            '--locales' => 'en',
        ]);

        $this->assertSame(0, $tester->getStatusCode());
        $this->assertCount(1, $dispatched);
        $this->assertSame(TranslatableEntityType::AUTHOR, $dispatched[0]->entityType);
        $this->assertSame(42, $dispatched[0]->entityId);
        $this->assertSame(['en'], $dispatched[0]->locales);
        $this->assertFalse($dispatched[0]->force);
    }
}
