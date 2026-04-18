<?php

declare(strict_types=1);

namespace App\Dto\Editorial;

/**
 * Output of {@see \App\Service\Editorial\Verification\SourceAttributionExtractor}
 * (Sprint 54 T54.7). Persisted onto {@see \App\Entity\Editorial\SourceSignal}
 * via `setSourceAttribution()` and `setSourceLinksOut()`.
 *
 * - `sourceAttribution`: textual mentions of the claim's origin extracted from
 *   the signal body ("potrivit Reuters", "surse diplomatice", "raportul
 *   Bellingcat"). `null` when the signal contains no explicit attribution.
 * - `linksOut`: external URLs found in the signal body, filtered to valid
 *   absolute URLs (navigation, share buttons, adverts are expected to be
 *   dropped by the LLM prompt contract).
 */
final readonly class SourceAttributionResult
{
    /**
     * @param list<string> $linksOut
     */
    public function __construct(
        public ?string $sourceAttribution,
        public array $linksOut,
    ) {}

    public static function empty(): self
    {
        return new self(null, []);
    }

    public function isEmpty(): bool
    {
        return $this->sourceAttribution === null && $this->linksOut === [];
    }
}
