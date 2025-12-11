<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\CleanupUnusedTagsMessage;
use App\Message\RecalculateTagCountsMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Schedule provider for tag maintenance tasks.
 *
 * Defines periodic tasks for cleaning up unused tags and recalculating counts.
 *
 * To run the scheduler:
 * symfony console messenger:consume scheduler_default
 *
 * Or use a cron job:
 * * * * * * cd /var/www/deschide_news_app/deschide_backend && php bin/console messenger:consume scheduler_default
 */
#[AsSchedule('tag_maintenance')]
class TagMaintenanceScheduleProvider implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return new Schedule()
            // Cleanup unused tags daily at 3:00 AM
            ->add(
                RecurringMessage::cron(
                    '0 3 * * *', // Daily at 3 AM
                    new CleanupUnusedTagsMessage(
                        daysOld: 30,
                        dryRun: false
                    )
                )
            )
            // Recalculate tag counts weekly on Sunday at 4:00 AM
            ->add(
                RecurringMessage::cron(
                    '0 4 * * 0', // Every Sunday at 4 AM
                    new RecalculateTagCountsMessage()
                )
            );
    }
}
