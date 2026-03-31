<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Enum\TranslationPriority;
use App\Message\TranslatePendingBatch;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Schedules batch translation dispatching for P2 (HIGH) and P3 (NORMAL) articles.
 *
 * P0 (CRITICAL) and P1 (URGENT) are dispatched instantly by
 * ArticleTranslationTriggerSubscriber — they do not need batch scheduling.
 *
 * To run: php bin/console messenger:consume scheduler_translation -vv
 */
#[AsSchedule('translation')]
class TranslationScheduleProvider implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            // P2 HIGH (editoriale): check every 5 minutes, dispatch up to 3
            ->add(
                RecurringMessage::every('5 minutes', new TranslatePendingBatch(
                    priority: TranslationPriority::HIGH,
                    limit: 3,
                ))
            )
            // P3 NORMAL (all other): check every 15 minutes, dispatch up to 5
            ->add(
                RecurringMessage::every('15 minutes', new TranslatePendingBatch(
                    priority: TranslationPriority::NORMAL,
                    limit: 5,
                ))
            );
    }
}
