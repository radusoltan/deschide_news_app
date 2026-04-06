<?php

declare(strict_types=1);

namespace App\Service\Aggregator;

use App\Dto\Aggregator\TrendQuery;
use App\Entity\Topic;
use App\Enum\AggregatorSourceType;
use App\Repository\TopicRepository;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Translation;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

readonly class TrendQueryGeneratorService
{
    private const CACHE_KEY = 'trend_queries';
    private const CACHE_TTL = 21600; // 6 hours
    private const RELEVANCE_CACHE_KEY = 'trend_relevance_keywords';

    public function __construct(
        private TrendScoringService $scoringService,
        private TopicRepository $topicRepository,
        private EntityManagerInterface $em,
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {}

    /**
     * Generate aggregator queries from trending topics.
     *
     * @return TrendQuery[]
     */
    public function generateQueries(int $topN = 20): array
    {
        $trending = $this->scoringService->getTopTrendingTopics(days: 7, limit: $topN);

        if (empty($trending)) {
            $this->logger->info('TrendQueryGenerator: no trending topics, returning empty queries');

            return [];
        }

        $queries = [];

        foreach ($trending as $topicData) {
            $topic = $this->topicRepository->find($topicData['topicId']);
            if ($topic === null) {
                continue;
            }

            $translations = $this->getTopicTranslations($topic);
            $keywords = array_filter(array_values($translations));

            if (empty($keywords)) {
                continue;
            }

            $score = $topicData['score'];

            // Generate queries per aggregator type
            foreach ($this->getAggregatorFormats($keywords, $translations) as [$type, $locale, $formatted]) {
                $queries[] = new TrendQuery(
                    topicId: $topicData['topicId'],
                    topicName: $topicData['topicName'],
                    keywords: $keywords,
                    locale: $locale,
                    aggregatorType: $type,
                    formattedQuery: $formatted,
                    trendScore: $score,
                );
            }
        }

        $this->logger->info('TrendQueryGenerator: generated {count} queries from {topics} topics', [
            'count' => \count($queries),
            'topics' => \count($trending),
        ]);

        return $queries;
    }

    /**
     * Export top trending topics as simple keywords for RelevanceFilterService.
     *
     * @return string[]
     */
    public function getRelevanceKeywords(): array
    {
        return $this->cache->get(self::RELEVANCE_CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(self::CACHE_TTL);

            $trending = $this->scoringService->getTopTrendingTopics(days: 7, limit: 20);
            $keywords = [];

            foreach ($trending as $topicData) {
                $topic = $this->topicRepository->find($topicData['topicId']);
                if ($topic === null) {
                    continue;
                }

                $translations = $this->getTopicTranslations($topic);
                foreach ($translations as $name) {
                    if ($name !== '' && $name !== null) {
                        $keywords[] = $name;
                    }
                }
            }

            return array_values(array_unique($keywords));
        });
    }

    /**
     * Get cached queries (previously generated).
     *
     * @return TrendQuery[]
     */
    public function getCachedQueries(): array
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): array {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->generateQueries();
        });
    }

    /**
     * Cache the given queries in Redis.
     *
     * @param TrendQuery[] $queries
     */
    public function cacheQueries(array $queries): void
    {
        $this->cache->delete(self::CACHE_KEY);
        $this->cache->delete(self::RELEVANCE_CACHE_KEY);

        $this->cache->get(self::CACHE_KEY, function (ItemInterface $item) use ($queries): array {
            $item->expiresAfter(self::CACHE_TTL);

            return $queries;
        });

        $this->logger->info('TrendQueryGenerator: cached {count} queries (TTL {ttl}s)', [
            'count' => \count($queries),
            'ttl' => self::CACHE_TTL,
        ]);
    }

    /**
     * Get topic title translations from Gedmo ext_translations.
     *
     * @return array<string, string> locale => title
     */
    private function getTopicTranslations(Topic $topic): array
    {
        $translationRepo = $this->em->getRepository(Translation::class);
        $translations = $translationRepo->findTranslations($topic);

        $result = [];
        // Default locale (ro) comes from the entity itself
        $roTitle = $topic->getTitle();
        if ($roTitle !== null && $roTitle !== '') {
            $result['ro'] = $roTitle;
        }

        foreach (['en', 'ru'] as $locale) {
            if (isset($translations[$locale]['title']) && $translations[$locale]['title'] !== '') {
                $result[$locale] = $translations[$locale]['title'];
            }
        }

        return $result;
    }

    /**
     * Format queries for each aggregator type.
     *
     * @param string[]                $keywords
     * @param array<string, string>   $translations locale => title
     *
     * @return list<array{AggregatorSourceType, string, string}>
     */
    private function getAggregatorFormats(array $keywords, array $translations): array
    {
        $formats = [];

        // Google News RSS: URL-encoded keyword (use RO first, then EN)
        $gnKeyword = $translations['ro'] ?? $translations['en'] ?? $keywords[0] ?? '';
        if ($gnKeyword !== '') {
            $formats[] = [
                AggregatorSourceType::GOOGLE_NEWS_RSS,
                'ro',
                rawurlencode($gnKeyword),
            ];
        }

        // NewsAPI: boolean OR query with quoted keywords (EN preferred)
        $newsApiParts = [];
        foreach ($translations as $name) {
            $newsApiParts[] = '"' . $name . '"';
        }
        if (!empty($newsApiParts)) {
            $formats[] = [
                AggregatorSourceType::NEWS_API,
                'en',
                implode(' OR ', $newsApiParts),
            ];
        }

        // Bing News: keyword + market (EN for international reach)
        $bingKeyword = $translations['en'] ?? $translations['ro'] ?? $keywords[0] ?? '';
        if ($bingKeyword !== '') {
            $formats[] = [
                AggregatorSourceType::BING_NEWS,
                'en',
                $bingKeyword,
            ];
        }

        return $formats;
    }
}
