<?php

declare(strict_types=1);

namespace App\Dto\LiveText;

use DateTimeInterface;

/**
 * Base DTO for LiveText Mercure events.
 */
abstract class LiveTextEventDto
{
    public function __construct(
        public readonly string $type,
        public readonly int $liveTextId,
        public readonly DateTimeInterface $timestamp
    ) {
    }

    abstract public function toArray(): array;
}
