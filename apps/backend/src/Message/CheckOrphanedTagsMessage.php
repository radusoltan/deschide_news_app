<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message for checking and cleaning orphaned tags.
 *
 * This message is dispatched after article deletion to check if any tags
 * became orphaned (usageCount = 0) and can be cleaned up.
 * Typically triggered by events rather than scheduled.
 */
final class CheckOrphanedTagsMessage
{
    public function __construct(
        private readonly array $tagIds = []
    ) {
    }

    /**
     * Get specific tag IDs to check, or empty array to check all unused tags.
     *
     * @return array<int>
     */
    public function getTagIds(): array
    {
        return $this->tagIds;
    }
}
