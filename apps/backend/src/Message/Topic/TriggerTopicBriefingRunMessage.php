<?php

declare(strict_types=1);

namespace App\Message\Topic;

use App\Enum\BriefingCadence;

/**
 * Scheduled trigger that iterates active topics, applies the eligibility gate,
 * and dispatches GenerateTopicBriefingMessage for each eligible topic.
 *
 * Dispatched by EditorialScheduleProvider on hourly/daily/weekly cadences.
 */
final readonly class TriggerTopicBriefingRunMessage
{
    public function __construct(
        public BriefingCadence $cadence,
    ) {}
}
