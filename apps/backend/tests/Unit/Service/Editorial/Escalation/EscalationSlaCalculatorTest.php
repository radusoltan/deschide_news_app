<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Escalation;

use App\Repository\AppSettingRepository;
use App\Service\Editorial\Escalation\EscalationSlaCalculator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for {@see EscalationSlaCalculator} (Sprint 55 T55.8).
 */
class EscalationSlaCalculatorTest extends TestCase
{
    private AppSettingRepository&MockObject $appSettingRepository;
    private EscalationSlaCalculator $calculator;

    protected function setUp(): void
    {
        $this->appSettingRepository = $this->createMock(AppSettingRepository::class);
        $this->appSettingRepository->method('getInt')
            ->willReturnCallback(fn (string $key, int $default): int => match ($key) {
                'editorial.escalation.sla_day_start_hour' => 7,
                'editorial.escalation.sla_day_end_hour' => 22,
                'editorial.escalation.sla_day_seconds' => 600,
                'editorial.escalation.sla_night_seconds' => 1800,
                default => $default,
            });

        $this->calculator = new EscalationSlaCalculator($this->appSettingRepository);
    }

    public function testDayWindowUses600SecondTtl(): void
    {
        // 14:30 UTC → 17:30 Europe/Chișinău (summer UTC+3) — firmly in day window.
        // Even if DST boundary shifts, 14:30 UTC in autumn is 16:30 CHI → still day.
        $now = new \DateTimeImmutable('2026-04-20T14:30:00+00:00');

        $expires = $this->calculator->computeExpiresAt($now);

        $this->assertSame(600, $expires->getTimestamp() - $now->getTimestamp());
    }

    public function testNightWindowUses1800SecondTtl(): void
    {
        // 00:00 UTC → 03:00 Europe/Chișinău — firmly in night window.
        $now = new \DateTimeImmutable('2026-04-20T00:00:00+00:00');

        $expires = $this->calculator->computeExpiresAt($now);

        $this->assertSame(1800, $expires->getTimestamp() - $now->getTimestamp());
    }

    public function testDayWindowStartBoundaryInclusive(): void
    {
        // 07:00 local is day (inclusive). Europe/Chișinău DST: spring=UTC+3,
        // winter=UTC+2. Use a timezone-specific string to avoid DST guesswork.
        $local = new \DateTimeImmutable('2026-04-20 07:00:00', new \DateTimeZone('Europe/Chisinau'));
        $expires = $this->calculator->computeExpiresAt($local);

        $this->assertSame(600, $expires->getTimestamp() - $local->getTimestamp(), '07:00 local = day window');
    }

    public function testDayWindowEndBoundaryExclusive(): void
    {
        // 22:00 local is already night (exclusive end).
        $local = new \DateTimeImmutable('2026-04-20 22:00:00', new \DateTimeZone('Europe/Chisinau'));
        $expires = $this->calculator->computeExpiresAt($local);

        $this->assertSame(1800, $expires->getTimestamp() - $local->getTimestamp(), '22:00 local = night window');
    }

    public function testDayWindowJustBefore22IsStillDay(): void
    {
        $local = new \DateTimeImmutable('2026-04-20 21:59:59', new \DateTimeZone('Europe/Chisinau'));
        $expires = $this->calculator->computeExpiresAt($local);

        $this->assertSame(600, $expires->getTimestamp() - $local->getTimestamp());
    }

    public function testTimezoneArgumentOverridesDefault(): void
    {
        // Passing an explicit timezone shifts the hour-bucket calculation.
        // UTC 05:00 is 05:00 UTC (night) but 08:00 Europe/Chișinău (day).
        $now = new \DateTimeImmutable('2026-04-20T05:00:00+00:00');

        $nightViaUtc = $this->calculator->computeExpiresAt($now, 'UTC');
        $this->assertSame(1800, $nightViaUtc->getTimestamp() - $now->getTimestamp());

        $dayViaChisinau = $this->calculator->computeExpiresAt($now, 'Europe/Chisinau');
        $this->assertSame(600, $dayViaChisinau->getTimestamp() - $now->getTimestamp());
    }

    public function testDefaultsWhenAppSettingsMissing(): void
    {
        $empty = $this->createMock(AppSettingRepository::class);
        $empty->method('getInt')->willReturnCallback(fn (string $key, int $default): int => $default);

        $calc = new EscalationSlaCalculator($empty);
        $now = new \DateTimeImmutable('2026-04-20T14:30:00+00:00');

        $expires = $calc->computeExpiresAt($now);
        // Default day TTL = 600.
        $this->assertSame(600, $expires->getTimestamp() - $now->getTimestamp());
    }

    public function testReturnedTimestampPreservesTimezoneOfNow(): void
    {
        // Consumers rely on the returned DateTimeImmutable's tz being the same
        // as the input `now`, so they can format consistently.
        $now = new \DateTimeImmutable('2026-04-20T14:30:00+00:00');
        $expires = $this->calculator->computeExpiresAt($now);

        $this->assertSame($now->getTimezone()->getName(), $expires->getTimezone()->getName());
    }
}
