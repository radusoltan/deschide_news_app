<?php

declare(strict_types=1);

namespace App\Message\Editorial;

/**
 * Dispatched by {@see \App\MessageHandler\Editorial\VerifyClaimMessageHandler}
 * (Sprint 55 T55.9) when a verified signal cluster targets a Topic that
 * already has an active {@see \App\Enum\ArticleType::DEVELOPING_STORY}
 * Article. The handler merges the new cluster into the existing record
 * instead of creating a fresh flash — preserves ADR-020 D9 single-record-
 * of-truth per developing topic.
 *
 * Routed to the same `editorial_flash` transport as {@see WriteFlashMessage}
 * (T55.3 infrastructure reuse — two cheaper-than-expected handlers share a
 * 2-worker pool).
 *
 * @internal
 */
final readonly class WriteDevelopingStoryMessage
{
    /**
     * @param int       $articleId           `articles.id` of the existing developing-story row
     * @param int       $primarySignalId     `source_signals.id` of the lead signal in the NEW cluster
     * @param list<int> $supportingSignalIds Other `source_signals.id` rows in the new cluster
     * @param string    $verdictType         {@see \App\Enum\Editorial\VerdictType}->value
     */
    public function __construct(
        public int $articleId,
        public int $primarySignalId,
        public array $supportingSignalIds,
        public string $verdictType,
    ) {}
}
