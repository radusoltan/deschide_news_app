<?php

declare(strict_types=1);

namespace App\Dto\Editorial;

/**
 * DTO representing an AI-generated article draft.
 *
 * Produced by ArticleWriterService — either via the legacy
 * StoryCluster path (clusterId set) or the Sprint 52 Topic+Window
 * path (topicId set). Consumed by CLI commands and (later) the
 * editorial dashboard.
 *
 * Both clusterId + topicId are nullable to support both call paths
 * during the T52.10 dual-codebase window. clusterId is removed
 * wholesale in T52.12 alongside the StoryCluster code.
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
        public ?int $clusterId,
        public float $confidenceScore,
        public array $sourcePressReleaseIds,
        public string $rawPrompt,
        public string $rawResponse,
        public ?int $topicId = null,
    ) {}
}
