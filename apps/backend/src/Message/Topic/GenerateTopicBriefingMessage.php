<?php

declare(strict_types=1);

namespace App\Message\Topic;

use App\Enum\BriefingCadence;

/**
 * Per-topic briefing generation work dispatched by TriggerTopicBriefingRunHandler.
 */
final readonly class GenerateTopicBriefingMessage
{
    public function __construct(
        public int $topicId,
        public BriefingCadence $cadence,
        public string $periodFrom,
        public string $periodTo,
    ) {}
}
