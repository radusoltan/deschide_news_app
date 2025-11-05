<?php

declare(strict_types=1);

namespace App\Dto\LiveText;

use DateTimeInterface;

/**
 * DTO for post.deleted event.
 */
class PostDeletedEventDto extends LiveTextEventDto
{
    public function __construct(
        int $liveTextId,
        public readonly int $postId,
        DateTimeInterface $timestamp
    ) {
        parent::__construct('post.deleted', $liveTextId, $timestamp);
    }

    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'liveTextId' => $this->liveTextId,
            'timestamp' => $this->timestamp->format(DateTimeInterface::ATOM),
            'postId' => $this->postId,
        ];
    }
}
