<?php

declare(strict_types=1);

namespace App\Dto\Topic;

final readonly class TopicProposal
{
    /**
     * @param string[] $keywords
     * @param int[]    $relatedArticleIds
     */
    public function __construct(
        public string $proposedNameRo,
        public string $proposedNameEn,
        public string $proposedNameRu,
        public array $keywords,
        public array $relatedArticleIds,
        public float $confidence,
        public ?string $suggestedParentTopic = null,
    ) {}
}
