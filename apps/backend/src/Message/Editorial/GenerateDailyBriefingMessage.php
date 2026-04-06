<?php

declare(strict_types=1);

namespace App\Message\Editorial;

final readonly class GenerateDailyBriefingMessage
{
    public function __construct(
        public string $type = 'evening',
        public ?string $date = null,
    ) {}
}
