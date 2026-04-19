<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\Editorial\ExpireEscalationsMessage;
use App\Repository\AppSettingRepository;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Editorial-pipeline SLA expiry scheduler (Sprint 55 T55.11, ADR-020 D9).
 *
 * Fires {@see ExpireEscalationsMessage} every 60 seconds while the pipeline
 * master switch is on. The handler marks
 * {@see \App\Entity\Editorial\EditorialEscalationLog} rows whose
 * `expires_at` is in the past as `decision=EXPIRED` and publishes a Mercure
 * event on the admin-escalations topic so the T55.13 UI drops them from
 * the pending queue without a full refetch.
 *
 * The 60-second cadence is intentionally coarse: SLA resolution is 10-minute
 * (day) / 30-minute (night) granularity, so a sub-minute tick would just
 * waste worker cycles. Keeps the scheduler transport light even when the
 * pipeline is busy.
 *
 * Pipeline-gated consistent with S53/S54 scheduler siblings — flipping
 * `editorial.pipeline.enabled=false` suspends expiry scanning entirely.
 *
 * To consume: symfony console messenger:consume scheduler_escalation_expiration -vv
 */
#[AsSchedule('escalation_expiration')]
final class EscalationExpirationScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(
        private readonly AppSettingRepository $appSettings,
    ) {}

    public function getSchedule(): Schedule
    {
        $schedule = new Schedule();

        if (!$this->appSettings->getBool('editorial.pipeline.enabled', false)) {
            return $schedule;
        }

        $schedule->add(RecurringMessage::every(
            '60 seconds',
            new ExpireEscalationsMessage(),
        ));

        return $schedule;
    }
}
