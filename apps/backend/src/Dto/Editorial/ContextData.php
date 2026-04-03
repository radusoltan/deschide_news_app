<?php

declare(strict_types=1);

namespace App\Dto\Editorial;

/**
 * Aggregated context data from multiple sources (ES, MOCs, atomic notes)
 * used for background paragraph generation.
 */
final class ContextData
{
    /**
     * @param list<array{id: int, score: float, source: array<string, mixed>}> $previousArticles
     * @param list<array{entity: string, content: string}> $atomicNotes
     */
    public function __construct(
        public array $previousArticles = [],
        public ?string $mocContent = null,
        public ?string $mocName = null,
        public array $atomicNotes = [],
    ) {}

    public function isEmpty(): bool
    {
        return $this->previousArticles === []
            && $this->mocContent === null
            && $this->atomicNotes === [];
    }

    public function sourcesCount(): int
    {
        return \count($this->previousArticles)
            + \count($this->atomicNotes)
            + ($this->mocContent !== null ? 1 : 0);
    }
}
