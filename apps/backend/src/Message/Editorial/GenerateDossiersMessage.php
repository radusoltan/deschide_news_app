<?php

declare(strict_types=1);

namespace App\Message\Editorial;

final readonly class GenerateDossiersMessage
{
    public function __construct(
        public int $threshold = 5,
        public int $days = 30,
    ) {}
}
