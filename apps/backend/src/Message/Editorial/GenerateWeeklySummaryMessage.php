<?php

declare(strict_types=1);

namespace App\Message\Editorial;

final readonly class GenerateWeeklySummaryMessage
{
    public function __construct(
        public ?string $weekEnd = null,
    ) {}
}
