<?php

declare(strict_types=1);

namespace App\Dto\LiveText;

use DateTimeInterface;

/**
 * DTO for viewers.count event.
 */
class ViewersCountEventDto extends LiveTextEventDto
{
    public function __construct(
        int $liveTextId,
        public readonly int $count,
        DateTimeInterface $timestamp
    ) {
        parent::__construct('viewers.count', $liveTextId, $timestamp);
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'liveTextId' => $this->liveTextId,
            'timestamp' => $this->timestamp->format(DateTimeInterface::ATOM),
            'count' => $this->count,
        ];
    }
}
