<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Enum\BriefingCadence;
use App\Message\Aggregator\TriggerAggregatorRunMessage;
use App\Message\ArchiveStalePressReleasesMessage;
use App\Message\Editorial\GenerateDossiersMessage;
use App\Message\Editorial\GenerateWeeklySummaryMessage;
use App\Message\Editorial\ScrapeSourceMessage;
use App\Message\NotebookLM\TriggerTopicNotebookSyncMessage;
use App\Message\Topic\TriggerTopicBriefingRunMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Schedules automated editorial content generation and scraping.
 *
 * To run: symfony console messenger:consume scheduler_editorial -vv
 */
#[AsSchedule('editorial')]
class EditorialScheduleProvider implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            // === Content Generation ===

            // Weekly summary on Sunday at 21:00
            ->add(RecurringMessage::cron(
                '0 21 * * 0',
                new GenerateWeeklySummaryMessage(),
            ))
            // Dossier generation on 1st and 15th of each month at 22:00
            ->add(RecurringMessage::cron(
                '0 22 1,15 * *',
                new GenerateDossiersMessage(threshold: 5, days: 30),
            ))

            // === Scraping Schedules (priority-based) ===

            // Priority 5: Local sources (Moldpres, IPN, Gov.md) — every 30 min
            ->add(RecurringMessage::every('30 minutes',
                new ScrapeSourceMessage(priorityGroup: 'local'),
            ))
            // Priority 3: High-priority international (Reuters, AP, UNIAN, Ukrinform) — every 2 hours
            ->add(RecurringMessage::every('2 hours',
                new ScrapeSourceMessage(priorityGroup: 'international_high'),
            ))
            // Priority 4: Medium-priority international (Agerpres, EC, Consilium, Europarl) — every 4 hours
            ->add(RecurringMessage::every('4 hours',
                new ScrapeSourceMessage(priorityGroup: 'international_medium'),
            ))

            // === Aggregator Schedules ===

            // Aggregator run every 2 hours (Sprint 27)
            ->add(RecurringMessage::every('2 hours',
                new TriggerAggregatorRunMessage(source: null, triggeredBy: 'scheduler'),
            ))

            // === Press Release Maintenance ===

            // Archive rejected PRs older than 30 days — daily at 04:00
            ->add(RecurringMessage::cron(
                '0 4 * * *',
                new ArchiveStalePressReleasesMessage(days: 30),
            ))

            // === Topic Briefing Schedules (Sprint 50, ADR-016) ===

            // Hourly briefing at minute 5 (avoids top-of-hour burst with other crons)
            ->add(RecurringMessage::cron(
                '5 * * * *',
                new TriggerTopicBriefingRunMessage(BriefingCadence::HOURLY),
            ))
            // Daily briefing at 22:00 Europe/Chisinau
            ->add(RecurringMessage::cron(
                '0 22 * * *',
                new TriggerTopicBriefingRunMessage(BriefingCadence::DAILY),
            ))
            // Weekly briefing on Sunday at 21:00 Europe/Chisinau
            ->add(RecurringMessage::cron(
                '0 21 * * 0',
                new TriggerTopicBriefingRunMessage(BriefingCadence::WEEKLY),
            ))

            // === NotebookLM Sync (Sprint 51a) ===

            // Sync topic sources to NotebookLM notebooks — daily at 02:00
            ->add(RecurringMessage::cron(
                '0 2 * * *',
                new TriggerTopicNotebookSyncMessage(),
            ));
    }
}
