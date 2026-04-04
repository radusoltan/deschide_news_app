<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\Editorial\GenerateDailyBriefingMessage;
use App\Message\Editorial\GenerateDossiersMessage;
use App\Message\Editorial\GenerateWeeklySummaryMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Schedules automated editorial content generation.
 *
 * To run: php bin/console messenger:consume scheduler_editorial -vv
 */
#[AsSchedule('editorial')]
class EditorialScheduleProvider implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            // Daily briefing at 20:00 (after day's articles are published)
            ->add(RecurringMessage::cron(
                '0 20 * * *',
                new GenerateDailyBriefingMessage(),
            ))
            // Weekly summary on Sunday at 21:00
            ->add(RecurringMessage::cron(
                '0 21 * * 0',
                new GenerateWeeklySummaryMessage(),
            ))
            // Dossier generation on 1st and 15th of each month at 22:00
            ->add(RecurringMessage::cron(
                '0 22 1,15 * *',
                new GenerateDossiersMessage(threshold: 5, days: 30),
            ));
    }
}
