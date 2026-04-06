<?php

declare(strict_types=1);

namespace App\Message\Editorial;

final readonly class ScrapeSourceMessage
{
    public function __construct(
        public ?string $sourceKey = null,
        public ?string $language = null,
        public int $limit = 30,
        public ?string $priorityGroup = null,
    ) {}
}
