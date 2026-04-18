<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\Editorial\RunMediaRoMonitorMessage;
use App\Message\Editorial\RunMediaRuMonitorMessage;
use App\Message\Editorial\RunWireMonitorMessage;
use App\Repository\AppSettingRepository;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Editorial-pipeline signal-collection scheduler (Sprint 53 T53.9, ADR-020 D4).
 *
 * The schedule is fully gated by `editorial.pipeline.enabled` in AppSettings —
 * when the master kill-switch is `false` (the Sprint 53 default), this provider
 * returns an empty Schedule and the two monitors never fire automatically.
 * Sprint 54 flips the flag on once the verification layer is ready.
 *
 * When enabled, the three monitors can be individually toggled and their
 * fetch intervals independently tuned via:
 *   - editorial.monitor.wire.enabled     / .fetch_interval_seconds (default 180s)
 *   - editorial.monitor.media_ro.enabled / .fetch_interval_seconds (default 300s)
 *   - editorial.monitor.media_ru.enabled / .fetch_interval_seconds (default 300s)
 *
 * To consume: symfony console messenger:consume scheduler_editorial_signal -vv
 */
#[AsSchedule('editorial_signal')]
final class EditorialSignalScheduleProvider implements ScheduleProviderInterface
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

        if ($this->appSettings->getBool('editorial.monitor.wire.enabled', true)) {
            $interval = max(30, $this->appSettings->getInt('editorial.monitor.wire.fetch_interval_seconds', 180));
            $schedule->add(RecurringMessage::every(
                sprintf('%d seconds', $interval),
                new RunWireMonitorMessage(),
            ));
        }

        if ($this->appSettings->getBool('editorial.monitor.media_ro.enabled', true)) {
            $interval = max(30, $this->appSettings->getInt('editorial.monitor.media_ro.fetch_interval_seconds', 300));
            $schedule->add(RecurringMessage::every(
                sprintf('%d seconds', $interval),
                new RunMediaRoMonitorMessage(),
            ));
        }

        if ($this->appSettings->getBool('editorial.monitor.media_ru.enabled', true)) {
            $interval = max(30, $this->appSettings->getInt('editorial.monitor.media_ru.fetch_interval_seconds', 300));
            $schedule->add(RecurringMessage::every(
                sprintf('%d seconds', $interval),
                new RunMediaRuMonitorMessage(),
            ));
        }

        return $schedule;
    }
}
