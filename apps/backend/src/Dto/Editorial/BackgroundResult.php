<?php

declare(strict_types=1);

namespace App\Dto\Editorial;

/**
 * Result of background paragraph generation.
 */
final readonly class BackgroundResult
{
    /**
     * @param list<int> $referencedArticles Article IDs referenced in background
     */
    public function __construct(
        public string $backgroundText,
        public array $referencedArticles = [],
        public ?string $mocUsed = null,
        public int $sourcesCount = 0,
    ) {}
}
