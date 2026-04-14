<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use App\Entity\Article;
use App\Repository\AppSettingRepository;

/**
 * Detects whether an article touches sensitive topics that require
 * human editorial review before publication.
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

        // Check 1: Topic-based sensitivity
        $sensitiveTopicSlugs = $this->settings->getJson(
            'auto_publish.sensitive_topics',
            self::DEFAULT_SENSITIVE_TOPICS,
        );

        foreach ($article->getTopics() as $topic) {
            $slug = $topic->getSlug();
            if ($slug !== null && \in_array($slug, $sensitiveTopicSlugs, true)) {
                $reasons[] = $slug;
            }
        }

        // Check 2: Category-based sensitivity
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
