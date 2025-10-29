<?php

declare(strict_types=1);

namespace App\Scheduler;

use App\Message\PublishScheduledArticles;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('default')]
final class ArticleScheduleProvider implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(
                // Run every minute to check for scheduled articles
                RecurringMessage::every('1 minute', new PublishScheduledArticles())
            );
    }
}
