<?php

declare(strict_types=1);

namespace App\Dto\Scraping;

final readonly class FeedItem
{
    public function __construct(
        public string $title,
        public string $url,
        public string $sourceName,
        public string $language,
        public ?string $description = null,
        public ?\DateTimeImmutable $publishedAt = null,
        public ?string $imageUrl = null,
    ) {}
}
