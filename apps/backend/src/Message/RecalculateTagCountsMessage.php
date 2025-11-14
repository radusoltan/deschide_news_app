<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message for recalculating tag usage counts.
 *
 * This message triggers a recalculation of usage counts for all tags
 * to ensure data consistency. Useful after bulk operations or data migrations.
 */
final class RecalculateTagCountsMessage
{
    public function __construct(
        private readonly ?int $tagId = null
    ) {
    }

    /**
     * Get specific tag ID to recalculate, or null for all tags.
     */
    public function getTagId(): ?int
    {
        return $this->tagId;
    }
}
