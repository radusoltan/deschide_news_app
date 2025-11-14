<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message for cleaning up unused tags.
 *
 * This message triggers the cleanup of tags that have not been used
 * for a specified number of days. Can be dispatched manually, by scheduler,
 * or triggered by events.
 */
final class CleanupUnusedTagsMessage
{
    public function __construct(
        private readonly int $daysOld = 30,
        private readonly bool $dryRun = false
    ) {
    }

    public function getDaysOld(): int
    {
        return $this->daysOld;
    }

    public function isDryRun(): bool
    {
        return $this->dryRun;
    }
}
