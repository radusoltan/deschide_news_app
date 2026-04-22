<?php

declare(strict_types=1);

namespace App\Dto\Aggregator;

final readonly class RemoteContentResult
{
    public function __construct(
        public string $title,
        public string $textContent,
        public string $htmlContent,
        public ?string $excerpt,
        public ?string $siteName,
        public ?string $imageUrl,
        public int $wordCount,
    ) {}
}
