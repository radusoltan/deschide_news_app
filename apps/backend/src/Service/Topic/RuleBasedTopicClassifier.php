<?php

declare(strict_types=1);

namespace App\Service\Topic;

use App\Entity\Article;
use App\Entity\Topic;
use App\Repository\TopicRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Maps articles to topics based on their category using static rules.
 * Handles deterministic categories (Politică → politica, Alegeri → alegeri, etc.)
 * without AI. Skips broad categories like Societate/Externe that need AI.
 */
class RuleBasedTopicClassifier
{
    /**
     * Category title → topic slug(s) mapping.
     * Empty array = skip (needs AI or is opinion/commercial content).
     *
     * @var array<string, list<string>>
     */
    private const CATEGORY_TO_TOPIC_SLUGS = [
        'Politică' => ['politica'],
        'Economie' => ['economie'],
        'Alegeri' => ['alegeri'],
        'România' => ['romania'],
        'Cultură' => ['cultura-si-societate'],
        'Sport' => ['sport'],
        'Transnistria' => ['transnistria'],
        'Anti-Fake' => ['media'],
        // Broad categories — delegate to AI
        'Societate' => [],
        'Externe' => [],
        // Opinion/commercial — skip entirely
        'Editoriale' => [],
        'Opinii' => [],
        'Advertorial' => [],
        'Dialog Deschis' => [],
    ];

    /** @var array<string, Topic> slug → Topic entity cache */
    private array $topicCache = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly TopicRepository $topicRepository,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Classify a single article by its category. Returns assigned topics.
     *
     * @return Topic[]
     */
    public function classify(Article $article): array
    {
        // Skip if article already has topics
        if (!$article->getTopics()->isEmpty()) {
            return [];
        }

        $category = $article->getCategory();
        if ($category === null) {
            return [];
        }

        $categoryTitle = $category->getTitle();
        $slugs = self::CATEGORY_TO_TOPIC_SLUGS[$categoryTitle] ?? [];

        if ($slugs === []) {
            return [];
        }

        $assigned = [];
        foreach ($slugs as $slug) {
            $topic = $this->resolveTopic($slug);
            if ($topic === null) {
                $this->logger->warning('Topic slug not found: {slug}', ['slug' => $slug]);
                continue;
            }
            $topic->addArticle($article);
            $assigned[] = $topic;
        }

        return $assigned;
    }

    /**
     * Classify a batch of articles. Flushes every $flushInterval articles.
     *
     * @param Article[] $articles
     */
    public function classifyBatch(array $articles, int $flushInterval = 100): int
    {
        $count = 0;
        $pending = 0;

        foreach ($articles as $article) {
            $assigned = $this->classify($article);
            if ($assigned !== []) {
                $count++;
                $pending++;
            }

            if ($pending >= $flushInterval) {
                $this->em->flush();
                $pending = 0;
            }
        }

        if ($pending > 0) {
            $this->em->flush();
        }

        return $count;
    }

    /**
     * Returns category titles that this classifier can handle (non-empty mapping).
     *
     * @return string[]
     */
    public static function getHandledCategories(): array
    {
        return array_keys(array_filter(
            self::CATEGORY_TO_TOPIC_SLUGS,
            static fn(array $slugs) => $slugs !== [],
        ));
    }

    /**
     * Returns category titles that need AI classification.
     *
     * @return string[]
     */
    public static function getAiCategories(): array
    {
        // Categories with empty mapping that are NOT opinion/commercial
        return ['Societate', 'Externe'];
    }

    private function resolveTopic(string $slug): ?Topic
    {
        if (!isset($this->topicCache[$slug])) {
            $this->topicCache[$slug] = $this->topicRepository->findOneBy(['slug' => $slug]);
        }

        return $this->topicCache[$slug];
    }
}
