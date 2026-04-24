<?php

declare(strict_types=1);

namespace App\Tests\Unit\ValueObject;

use App\Enum\BriefingCadence;
use App\ValueObject\DateRange;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DateRangeTest extends TestCase
{
    #[Test]
    public function itCreatesFromConstructor(): void
    {
        $from = new \DateTimeImmutable('2026-04-10');
        $to = new \DateTimeImmutable('2026-04-16');
        $range = new DateRange($from, $to);

        $this->assertSame($from, $range->from);
        $this->assertSame($to, $range->to);
    }

    #[Test]
    public function itCreatesLastDays(): void
    {
        $range = DateRange::lastDays(7);
        $diff = $range->to->getTimestamp() - $range->from->getTimestamp();

        // 7 days = 604800 seconds, allow 2 second tolerance
        $this->assertEqualsWithDelta(604800, $diff, 2);
    }

    #[Test]
    public function itCreatesLastHourWith70MinuteLookback(): void
    {
        $range = DateRange::lastHour();
        $diff = $range->to->getTimestamp() - $range->from->getTimestamp();

        // 70 minutes = 4200 seconds, allow 2 second tolerance
        $this->assertEqualsWithDelta(4200, $diff, 2);
    }

    #[Test]
    public function itCreatesLastDay(): void
    {
        $range = DateRange::lastDay();
        $diff = $range->to->getTimestamp() - $range->from->getTimestamp();

        // 24 hours = 86400 seconds, allow 2 second tolerance
        $this->assertEqualsWithDelta(86400, $diff, 2);
    }

    #[Test]
    public function itCreatesLastWeek(): void
    {
        $range = DateRange::lastWeek();
        $diff = $range->to->getTimestamp() - $range->from->getTimestamp();

        // 7 days = 604800 seconds, allow 2 second tolerance
        $this->assertEqualsWithDelta(604800, $diff, 2);
    }

    #[Test]
    public function itFormatsDateRange(): void
    {
        $range = new DateRange(
            new \DateTimeImmutable('2026-04-10'),
            new \DateTimeImmutable('2026-04-16'),
        );

        $this->assertSame('2026-04-10 to 2026-04-16', $range->format());
    }

    #[Test]
    public function itCreatesForHourlyCadence(): void
    {
        $range = DateRange::forCadence(BriefingCadence::HOURLY);
        $diff = $range->to->getTimestamp() - $range->from->getTimestamp();

        // 70 minutes = 4200 seconds
        $this->assertEqualsWithDelta(4200, $diff, 2);
    }

    #[Test]
    public function itCreatesForDailyCadence(): void
    {
        $range = DateRange::forCadence(BriefingCadence::DAILY);
        $diff = $range->to->getTimestamp() - $range->from->getTimestamp();

        // 24 hours = 86400 seconds
        $this->assertEqualsWithDelta(86400, $diff, 2);
    }

    #[Test]
    public function itCreatesForWeeklyCadence(): void
    {
        $range = DateRange::forCadence(BriefingCadence::WEEKLY);
        $diff = $range->to->getTimestamp() - $range->from->getTimestamp();

        // 7 days = 604800 seconds
        $this->assertEqualsWithDelta(604800, $diff, 2);
    }
}
