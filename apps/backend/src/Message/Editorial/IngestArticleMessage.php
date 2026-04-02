<?php

declare(strict_types=1);

namespace App\Message\Editorial;

/**
 * Dispatched after an article is created/scraped to trigger AI ingestion:
 * entity extraction, atomic notes, MOC updates, NotebookLM feed.
 */
final readonly class IngestArticleMessage
{
    public function __construct(
        public int $articleId,
    ) {}
}
