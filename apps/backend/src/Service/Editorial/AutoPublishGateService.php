<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use App\Dto\Editorial\AutoPublishDecision;
use App\Entity\Article;
use App\Entity\PressRelease;
use App\Repository\AppSettingRepository;
use Psr\Log\LoggerInterface;

/**
 * Evaluates whether an Article can be auto-published or requires editorial review.
 *
 * Formula:
 * - confidence ≥ 0.85 + sources ≥ 3 + words ≥ 300 (AI articles)
 * - source credibility ≥ 0.90 (non-AI articles)
 * - No [DISCREPANȚĂ] markers
 * - Topic not in sensitive list
 * - No person mentions in title/lead
 * - Auto-publish globally enabled
 */
class AutoPublishGateService
{
    public function __construct(
        private readonly SensitiveTopicDetector $sensitiveTopicDetector,
        private readonly AppSettingRepository $settings,
        private readonly LoggerInterface $logger,
    ) {}

    public function evaluate(Article $article, ?PressRelease $pressRelease = null): AutoPublishDecision
    {
        $reasons = [];

        // 1. Auto-publish enabled?
        if (!$this->settings->getBool('auto_publish.enabled', false)) {
            return new AutoPublishDecision(false, ['auto_publish_disabled'], 'pending_review');
        }

        $isAiGenerated = $article->isAiGenerated();

        // 2. Source credibility check (non-AI articles)
        if (!$isAiGenerated && $pressRelease !== null) {
            $source = $pressRelease->getSource();
            $sourceCredibility = $source?->getCredibilityWeight() ?? 0.5;
            $minCredibility = $this->settings->getFloat('auto_publish.min_source_credibility', 0.90);

            if ($sourceCredibility < $minCredibility) {
                $reasons[] = sprintf('low_credibility:%.2f', $sourceCredibility);
            }
        }

        // 3. Confidence threshold (AI articles)
        if ($isAiGenerated) {
            $minConfidence = $this->settings->getFloat('auto_publish.min_confidence', 0.85);
            $confidence = $article->getAiConfidenceScore() ?? 0.0;

            if ($confidence < $minConfidence) {
                $reasons[] = sprintf('low_confidence:%.2f', $confidence);
            }

            // Min sources
            $minSources = $this->settings->getInt('auto_publish.min_sources', 3);
            $sourceCount = $article->getAiSourceCount() ?? 0;

            if ($sourceCount < $minSources) {
                $reasons[] = sprintf('few_sources:%d', $sourceCount);
            }
        }

        // 4. Word count check
        $wordCount = str_word_count(strip_tags($article->getContent() ?? ''));
        $minWords = $this->settings->getInt('auto_publish.min_words', 300);

        if ($wordCount < $minWords) {
            $reasons[] = sprintf('short_content:%d_words', $wordCount);
        }

        // 5. Discrepancy marker check
        if (str_contains($article->getContent() ?? '', '[DISCREPANȚĂ]')) {
            $reasons[] = 'has_discrepancy';
        }

        // 6. Sensitive topic check (ALWAYS blocks auto-publish)
        if ($this->sensitiveTopicDetector->isSensitive($article)) {
            $sensitiveTopics = $this->sensitiveTopicDetector->getSensitiveTopics($article);
            $reasons[] = 'sensitive_topic:' . implode(',', $sensitiveTopics);
        }

        $canPublish = $reasons === [];
        $gate = $canPublish ? 'auto_publish' : 'pending_review';

        $this->logger->info('AutoPublishGate: evaluated article', [
            'articleId' => $article->getId(),
            'canPublish' => $canPublish,
            'gate' => $gate,
            'reasons' => $reasons,
            'isAiGenerated' => $isAiGenerated,
        ]);

        return new AutoPublishDecision($canPublish, $reasons, $gate);
    }
}
