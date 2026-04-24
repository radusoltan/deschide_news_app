<?php

declare(strict_types=1);

namespace App\Service\Editorial\Escalation;

use App\Repository\AppSettingRepository;

/**
 * Computes SLA expiry timestamps for EditorialEscalationLog rows
 * (Sprint 55 T55.8, audit D9 + D21).
 *
 * Day/night windows respect Europe/Chișinău local time per audit D21:
 *   · day window:   07:00 ≤ hour < 22:00 → short SLA (default 10 min)
 *   · night window: otherwise           → relaxed SLA (default 30 min)
 *
 * Windows + TTLs are seeded via AppSettings (T55.14):
 *   - editorial.escalation.sla_day_start_hour        (default 7)
 *   - editorial.escalation.sla_day_end_hour          (default 22)
 *   - editorial.escalation.sla_day_seconds           (default 600)
 *   - editorial.escalation.sla_night_seconds         (default 1800)
 *
 * Stateless service — pure function of (now, AppSettings). Callers pass
 * `now` explicitly so T55.11's ScheduleProvider can stub time in tests.
 */
class EscalationSlaCalculator
{
    public const DEFAULT_TIMEZONE = 'Europe/Chisinau';

    public function __construct(
        private readonly AppSettingRepository $appSettingRepository,
    ) {}

    public function computeExpiresAt(\DateTimeImmutable $now, string $timezone = self::DEFAULT_TIMEZONE): \DateTimeImmutable
    {
        $local = $now->setTimezone(new \DateTimeZone($timezone));
        $hour = (int) $local->format('H');

        $dayStart = $this->appSettingRepository->getInt('editorial.escalation.sla_day_start_hour', 7);
        $dayEnd = $this->appSettingRepository->getInt('editorial.escalation.sla_day_end_hour', 22);

        $isDayWindow = $hour >= $dayStart && $hour < $dayEnd;
        $ttl = $isDayWindow
            ? $this->appSettingRepository->getInt('editorial.escalation.sla_day_seconds', 600)
            : $this->appSettingRepository->getInt('editorial.escalation.sla_night_seconds', 1800);

        return $now->modify(sprintf('+%d seconds', $ttl));
    }
}
