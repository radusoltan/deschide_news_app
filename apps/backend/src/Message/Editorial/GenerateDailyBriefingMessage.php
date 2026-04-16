<?php

declare(strict_types=1);

namespace App\Message\Editorial;

/**
 * @deprecated since Sprint 50 (April 2026), will be removed in Sprint 52.
 *             Superseded by {@see \App\Message\Topic\TriggerTopicBriefingRunMessage}
 *             with cadence=daily. See ADR-016.
 */
final readonly class GenerateDailyBriefingMessage
{
    public function __construct(
        public string $type = 'evening',
        public ?string $date = null,
    ) {}
}
