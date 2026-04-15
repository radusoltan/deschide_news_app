<?php

declare(strict_types=1);

namespace App\Message\Scraping;

final readonly class ScrapeFullContentMessage
{
    public function __construct(
        public int $pressReleaseId,
        public string $sourceUrl,
    ) {}
}
