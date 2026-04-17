<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Per-topic article generation work dispatched by GenerateArticleCommand
 * (Sprint 52, ADR-019 D2).
 *
 * Handler (GenerateTopicArticleHandler) re-fetches the topic by id, calls
 * PressReleaseRepository::findByTopicInWindow, runs eligibility +
 * (optionally) semantic dedup, then delegates to ArticleWriterService.
 *
 * Routed to the shared `briefing` transport per ADR-019 D2 default.
 * If queue saturation surfaces post-merge, a parallel `article_generation`
 * transport can be added in messenger.yaml without code change here.
 */
final readonly class GenerateTopicArticleMessage
{
    public function __construct(
        public int $topicId,
        public \DateTimeImmutable $windowStart,
        public \DateTimeImmutable $windowEnd,
    ) {}
}
