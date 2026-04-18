<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\Editorial\FlushStabilizedSignalsMessage;
use App\Repository\AppSettingRepository;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Editorial-pipeline stabilization-flush scheduler (Sprint 54 T54.6, ADR-020 D2).
 *
 * Fires {@see FlushStabilizedSignalsMessage} every 10 seconds while the
 * pipeline master switch is on. Deliberately a separate schedule from
 * `editorial_signal` because the cadences serve different purposes:
 *   - editorial_signal   — slow (180s–300s) signal collection from RSS feeds
 *   - signal_stabilization — fast (10s) buffer sweep so no cluster lingers
 *     unnecessarily past its stabilization window
 *
 * To consume: symfony console messenger:consume scheduler_signal_stabilization -vv
 */
#[AsSchedule('signal_stabilization')]
final class SignalStabilizationScheduleProvider implements ScheduleProviderInterface
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
            '10 seconds',
            new FlushStabilizedSignalsMessage(),
        ));

        return $schedule;
    }
}
