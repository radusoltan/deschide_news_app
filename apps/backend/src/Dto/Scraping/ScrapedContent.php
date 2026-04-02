<?php

declare(strict_types=1);

namespace App\Dto\Scraping;

final readonly class ScrapedContent
{
    public function __construct(
        public string $url,
        public string $title,
        public string $bodyHtml,
        public string $bodyText,
        public string $language,
        public string $sourceName,
        public ?\DateTimeImmutable $publishedAt = null,
    ) {}
}
