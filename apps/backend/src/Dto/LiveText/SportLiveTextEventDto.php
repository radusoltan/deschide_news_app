<?php

declare(strict_types=1);

namespace App\Dto\LiveText;

use DateTimeInterface;

/**
 * Concrete DTO for sport-related LiveText Mercure events.
 *
 * Used by LiveTextNotificationService for sport score updates,
 * match status changes, match events, minute updates, and statistics.
 */
class SportLiveTextEventDto extends LiveTextEventDto
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(
        string $type,
        int $liveTextId,
        public readonly array $data,
        DateTimeInterface $timestamp
    ) {
        parent::__construct($type, $liveTextId, $timestamp);
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'liveTextId' => $this->liveTextId,
            'timestamp' => $this->timestamp->format(DateTimeInterface::ATOM),
            'data' => $this->data,
        ];
    }
}
