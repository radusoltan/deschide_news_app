<?php

declare(strict_types=1);

namespace App\Message\Editorial;

final readonly class ProcessScrapedArticleMessage
{
    public function __construct(
        public string $title,
        public string $bodyMarkdown,
        public string $sourceUrl,
        public string $sourceName,
        public string $originalLanguage,
        public string $contentHash,
        public ?\DateTimeImmutable $publishedAt = null,
    ) {}
}
