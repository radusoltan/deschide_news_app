<?php

declare(strict_types=1);

namespace App\Dto\Editorial;

/**
 * Editorial-context synthesis output (Sprint 54 T54.11, ADR-020 D2).
 *
 * Produced by {@see \App\Service\Editorial\Verification\ContextAgent} after
 * a cluster has been verified. Carries:
 *
 * - `relatedArticles`: top-N similar articles from the Elasticsearch index
 *   (tuples of id/title/publishedAt) so downstream writers can reference
 *   prior coverage without re-running MLT.
 * - `narrativeThread`: Sonnet-synthesized paragraph weaving the claim
 *   into its historical context. `null` when the claim is novel (no ES
 *   results) — the early-return path skips the LLM call entirely.
 * - `isNovelClaim`: true when the ES MLT returned zero hits. Surfaces
 *   the fact that we have no prior coverage to anchor the new story.
 *
 * Sprint 54 builds the agent but doesn't invoke it from the verification
 * pipeline — Sprint 55+ writers (ArticleWriterService, BackgroundProposal
 * generator) will pull from here.
 */
final readonly class EditorialContext
{
    /**
     * @param list<array{id: int, title: string, score: float}> $relatedArticles
     */
    public function __construct(
        public array $relatedArticles,
        public ?string $narrativeThread,
        public bool $isNovelClaim,
    ) {}

    public static function novel(): self
    {
        return new self([], null, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'related_articles' => $this->relatedArticles,
            'narrative_thread' => $this->narrativeThread,
            'is_novel_claim' => $this->isNovelClaim,
        ];
    }
}
