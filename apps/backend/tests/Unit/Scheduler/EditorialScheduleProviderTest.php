<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduler;

use App\Message\Aggregator\TriggerAggregatorRunMessage;
use App\Scheduler\EditorialScheduleProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Schedule;

class EditorialScheduleProviderTest extends TestCase
{
    private EditorialScheduleProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new EditorialScheduleProvider();
    }

    public function testGetScheduleReturnsScheduleInstance(): void
    {
        $schedule = $this->provider->getSchedule();

        $this->assertInstanceOf(Schedule::class, $schedule);
    }

    public function testScheduleContainsExpectedNumberOfRecurringMessages(): void
    {
        $schedule = $this->provider->getSchedule();
        $messages = $schedule->getRecurringMessages();

        // 4 content generation + 3 scraping + 1 aggregator = 8
        $this->assertCount(8, $messages);
    }

    public function testScheduleContainsTriggerAggregatorRunMessage(): void
    {
        $schedule = $this->provider->getSchedule();
        $messages = $schedule->getRecurringMessages();

        $aggregatorMessages = array_filter(
            $messages,
            fn ($recurring) => str_contains((string) $recurring->getProvider(), TriggerAggregatorRunMessage::class),
        );

        $this->assertCount(1, $aggregatorMessages, 'Schedule must contain exactly one TriggerAggregatorRunMessage');

        // Verify the message properties via the provider
        $provider = array_values($aggregatorMessages)[0]->getProvider();
        $context = new MessageContext(
            'editorial',
            'test',
            array_values($aggregatorMessages)[0]->getTrigger(),
            new \DateTimeImmutable(),
        );
        $extractedMessages = iterator_to_array($provider->getMessages($context));
        $this->assertCount(1, $extractedMessages);

        /** @var TriggerAggregatorRunMessage $message */
        $message = $extractedMessages[0];
        $this->assertInstanceOf(TriggerAggregatorRunMessage::class, $message);
        $this->assertNull($message->source, 'Aggregator should run for all sources (null)');
        $this->assertSame('scheduler', $message->triggeredBy, 'triggeredBy must be "scheduler"');
    }
}
