<?php

declare(strict_types=1);

namespace App\Dto\Aggregator;

use App\Enum\AggregatorSourceType;

final readonly class AggregatorResult
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
        public \DateTimeImmutable $publishedAt,
        public string $rawContent,
        public array $keywords = [],
        public ?AggregatorSourceType $aggregatorSourceType = null,
    ) {}
}
