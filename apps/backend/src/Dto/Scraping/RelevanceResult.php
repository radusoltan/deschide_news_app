<?php

declare(strict_types=1);

namespace App\Dto\Scraping;

final readonly class RelevanceResult
{
    /**
     * @param array<array{tier: int, keyword: string}> $matches
     */
    public function __construct(
        public bool $isRelevant,
        public int $score,
        public array $matches,
        public int $tier1Count,
        public int $tier2Count,
        public int $tier3Count,
    ) {}
}
