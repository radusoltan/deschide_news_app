<?php

declare(strict_types=1);

namespace App\Message\Scraping;

/**
 * Dispatched by scheduler or CLI to trigger a Python scraper run for a single source.
 */
final readonly class PythonScrapeMessage
{
    public function __construct(
        public string $sourceName,   // YAML key: "gov_md", "cancelaria", etc.
        public string $sourceUrl,    // URL from YAML config
        public string $sourceType,   // "gov-rss", "dom-scraper", "pdf-extractor"
        public int $limit = 20,
    ) {}
}
