<?php

declare(strict_types=1);

namespace App\Tests\Unit\Scheduler;

use App\Message\Editorial\ExpireEscalationsMessage;
use App\Repository\AppSettingRepository;
use App\Scheduler\EscalationExpirationScheduleProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Trigger\TriggerInterface;

/**
 * Unit test for {@see EscalationExpirationScheduleProvider} (Sprint 55 T55.11).
 */
class EscalationExpirationScheduleProviderTest extends TestCase
{
    private AppSettingRepository&MockObject $appSettings;
    private EscalationExpirationScheduleProvider $provider;

    protected function setUp(): void
    {
        $this->appSettings = $this->createMock(AppSettingRepository::class);
        $this->provider = new EscalationExpirationScheduleProvider($this->appSettings);
    }

    public function testPipelineDisabledProducesEmptySchedule(): void
    {
        $this->appSettings->method('getBool')
            ->with('editorial.pipeline.enabled', false)
            ->willReturn(false);

        $schedule = $this->provider->getSchedule();

        $this->assertCount(0, $schedule->getRecurringMessages());
    }

    public function testPipelineEnabledProducesRecurringMessage(): void
    {
        $this->appSettings->method('getBool')
            ->with('editorial.pipeline.enabled', false)
            ->willReturn(true);

        $schedule = $this->provider->getSchedule();

        $messages = $schedule->getRecurringMessages();
        $this->assertCount(1, $messages);

        // Drive the provider via a MessageContext to get the actual envelope
        // back — this mirrors what the scheduler runtime does per tick.
        $provider = $messages[0]->getProvider();
        $trigger = $this->createMock(TriggerInterface::class);
        $context = new MessageContext(
            'escalation_expiration',
            'expire_escalations',
            $trigger,
            new \DateTimeImmutable(),
        );

        $emitted = iterator_to_array($provider->getMessages($context), false);
        $this->assertCount(1, $emitted);
        $this->assertInstanceOf(ExpireEscalationsMessage::class, $emitted[0]);
    }
}
