<?php

declare(strict_types=1);

namespace App\Dto\Editorial;

/**
 * DTO representing an AI-generated article draft from a StoryCluster.
 *
 * Produced by ArticleWriterService, consumed by CLI commands
 * and (later) the editorial dashboard.
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
        public int $clusterId,
        public float $confidenceScore,
        public array $sourcePressReleaseIds,
        public string $rawPrompt,
        public string $rawResponse,
    ) {}
}
