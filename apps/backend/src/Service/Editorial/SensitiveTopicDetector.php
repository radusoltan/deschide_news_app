<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use App\Entity\Article;
use App\Repository\AppSettingRepository;

/**
 * Detects whether an article touches sensitive topics that require
 * human editorial review before publication.
 *
 * @deprecated Sprint 49 — Check 1 now uses Topic.isSensitive boolean (ADR-015).
 *             Slug-based matching remains as fallback for articles without topic tags.
 *             Full removal planned Sprint 52 when all articles are topic-tagged.
 */
class SensitiveTopicDetector
{
    private const DEFAULT_SENSITIVE_TOPICS = [
        'politica',
        'alegeri',
        'transnistria',
        'gagauzia',
        'externe',
        'opinii',
        'editoriale',
    ];

    /** Common non-person multi-word capitalized patterns to exclude */
    private const EXCLUDE_PATTERNS = [
        'Republica Moldova',
        'Uniunea Europeană',
        'Federația Rusă',
        'Statele Unite',
        'Parlamentul Republicii',
        'Guvernul Republicii',
        'Curtea Constituțională',
        'Consiliul Europei',
        'Organizația Națiunilor',
        'Banca Națională',
        'Inspectoratul General',
    ];

    public function __construct(
        private readonly AppSettingRepository $settings,
    ) {}

    public function isSensitive(Article $article): bool
    {
        return $this->getSensitiveTopics($article) !== [];
    }

    /**
     * @return list<string> List of sensitive topic slugs or detection reasons
     */
    public function getSensitiveTopics(Article $article): array
    {
        $reasons = [];

        // The slug list is consulted by Check 2 (category-based sensitivity)
        // unconditionally — Check 1 (Topic.isSensitive) and Check 2 are
        // independent dimensions and the article may match either or both.
        // Hoisted out of the if/else below so the variable is always defined
        // even when the article already has topics (the previous structure
        // only assigned it in the else branch, leaving Check 2 to read
        // an undefined variable when topics existed).
        $sensitiveTopicSlugs = $this->settings->getJson(
            'auto_publish.sensitive_topics',
            self::DEFAULT_SENSITIVE_TOPICS,
        );

        // Check 1: Topic-based sensitivity via Topic.isSensitive boolean (ADR-015)
        // TODO Sprint 52 (T60.18): once every article is topic-tagged, this
        // boolean check supersedes Check 2 and the slug list can retire.
        if (!$article->getTopics()->isEmpty()) {
            // Primary path: read isSensitive directly from Topic entity
            foreach ($article->getTopics() as $topic) {
                if ($topic->isSensitive()) {
                    $reasons[] = $topic->getSlug() ?? 'sensitive_topic';
                }
            }
        }

        // Check 2: Category-based sensitivity (legacy slug-list fallback)
        $category = $article->getCategory();
        if ($category !== null) {
            $categorySlug = $category->getSlug();
            if ($categorySlug !== null && \in_array($categorySlug, $sensitiveTopicSlugs, true)) {
                $reasons[] = 'category:' . $categorySlug;
            }
        }

        // Check 3: Person-mention detection in title/lead
        $titleLead = ($article->getTitle() ?? '') . ' ' . ($article->getLead() ?? '');
        if ($this->containsPersonMention($titleLead)) {
            $reasons[] = 'person_mention';
        }

        return array_values(array_unique($reasons));
    }

    private function containsPersonMention(string $text): bool
    {
        // Remove known non-person patterns to avoid false positives
        foreach (self::EXCLUDE_PATTERNS as $exclude) {
            $text = str_replace($exclude, '', $text);
        }

        // Match "Firstname Lastname" patterns — two consecutive capitalized words
        // Romanian diacritics included in the character class
        return (bool) preg_match(
            '/\b[A-ZĂÂÎȘȚ][a-zăâîșț]{2,}\s+[A-ZĂÂÎȘȚ][a-zăâîșț]{2,}\b/u',
            $text,
        );
    }
}
