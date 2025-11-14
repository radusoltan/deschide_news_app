<?php

declare(strict_types=1);

namespace App\Dto\LiveText;

use App\Entity\LiveText;
use DateTime;
use DateTimeInterface;

/**
 * DTO for status.changed event.
 */
class StatusChangedEventDto extends LiveTextEventDto
{
    public function __construct(
        int $liveTextId,
        public readonly string $status,
        public readonly string $title,
        DateTimeInterface $timestamp
    ) {
        parent::__construct('status.changed', $liveTextId, $timestamp);
    }

    public static function fromEntity(LiveText $liveText): self
    {
        return new self(
            liveTextId: $liveText->getId(),
            status: $liveText->getStatus()->value,
            title: $liveText->getTitle(),
            timestamp: new DateTime()
        );
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'liveTextId' => $this->liveTextId,
            'timestamp' => $this->timestamp->format(DateTimeInterface::ATOM),
            'status' => $this->status,
            'title' => $this->title,
        ];
    }
}
