<?php

declare(strict_types=1);

namespace App\Dto\Editorial;

/**
 * DTO representing an AI-generated article draft.
 *
 * Produced by ArticleWriterService via the Topic + Window path (ADR-019 D2).
 * Consumed by CLI commands and (later) the editorial dashboard.
 */
final readonly class ArticleDraft
{
    /**
     * @param list<string> $suggestedTags
     * @param list<int>    $sourcePressReleaseIds
     */
    public function __construct(
        public string $titleRo,
        public string $leadRo,
        public string $contentRo,
        public string $metaDescription,
        public array $suggestedTags,
        public float $confidenceScore,
        public array $sourcePressReleaseIds,
        public string $rawPrompt,
        public string $rawResponse,
        public ?int $topicId = null,
    ) {}
}
