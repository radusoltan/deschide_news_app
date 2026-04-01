<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Schedule;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Contracts\Cache\CacheInterface;

class ScheduleTest extends TestCase
{
    public function testGetScheduleReturnsSymfonySchedule(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $schedule = new Schedule($cache);

        $result = $schedule->getSchedule();

        $this->assertInstanceOf(SymfonySchedule::class, $result);
    }

    public function testScheduleContainsRecurringMessages(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $schedule = new Schedule($cache);

        $result = $schedule->getSchedule();

        // The schedule should have at least one recurring message (PublishScheduledArticles)
        $messages = $result->getRecurringMessages();
        $this->assertNotEmpty($messages);
    }

    public function testScheduleImplementsScheduleProviderInterface(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $schedule = new Schedule($cache);

        $this->assertInstanceOf(\Symfony\Component\Scheduler\ScheduleProviderInterface::class, $schedule);
    }
}
