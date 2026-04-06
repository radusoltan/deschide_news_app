<?php

declare(strict_types=1);

namespace App\Dto\Aggregator;

use App\Enum\AggregatorSourceType;

final readonly class TrendQuery
{
    /**
     * @param string[] $keywords
     */
    public function __construct(
        public int $topicId,
        public string $topicName,
        public array $keywords,
        public string $locale,
        public AggregatorSourceType $aggregatorType,
        public string $formattedQuery,
        public float $trendScore,
    ) {}
}
