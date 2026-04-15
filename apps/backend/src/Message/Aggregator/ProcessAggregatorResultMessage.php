<?php

declare(strict_types=1);

namespace App\Message\Aggregator;

final readonly class ProcessAggregatorResultMessage
{
    /**
     * @param string[] $keywords
     */
    public function __construct(
        public string $title,
        public string $summary,
        public string $sourceUrl,
        public string $sourceLanguage,
        public string $sourceName,
        public string $publishedAt,
        public string $rawContent,
        public array $keywords,
        public string $aggregatorSourceType,
        public ?string $sourcePublisherDomain = null,
    ) {}
}
