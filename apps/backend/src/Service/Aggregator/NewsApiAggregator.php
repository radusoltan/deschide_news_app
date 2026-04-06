<?php

declare(strict_types=1);

namespace App\Service\Aggregator;

use App\Dto\Aggregator\AggregatorResult;
use App\Enum\AggregatorSourceType;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AutoconfigureTag('app.aggregator')]
final readonly class NewsApiAggregator implements AggregatorInterface
{
    private const ENDPOINT = 'https://newsapi.org/v2/everything';
    private const MAX_DAILY_REQUESTS = 95; // safety margin below 100
    private const COUNTER_KEY = 'newsapi_daily_count';
    private const COUNTER_TTL = 86400; // 24h

    public function __construct(
        private HttpClientInterface $httpClient,
        private TrendQueryGeneratorService $trendQueryGenerator,
        private CacheInterface $cache,
        private LoggerInterface $logger,
        #[Autowire(env: 'NEWSAPI_KEY')]
        private string $apiKey = '',
    ) {}

    public function fetch(): array
    {
        if ($this->apiKey === '') {
            $this->logger->info('NewsApiAggregator: no API key configured, skipping.');

            return [];
        }

        $queries = $this->trendQueryGenerator->getCachedQueries();
        $newsApiQueries = array_filter(
            $queries,
            fn ($q) => $q->aggregatorType === AggregatorSourceType::NEWS_API,
        );

        if (empty($newsApiQueries)) {
            $this->logger->info('NewsApiAggregator: no trend queries for NEWS_API.');

            return [];
        }

        $results = [];
        $yesterday = (new \DateTimeImmutable('-1 day'))->format('Y-m-d');
        $today = (new \DateTimeImmutable())->format('Y-m-d');

        foreach ($newsApiQueries as $trendQuery) {
            if (!$this->checkDailyLimit()) {
                $this->logger->warning('NewsApiAggregator: daily limit reached, stopping.');
                break;
            }

            try {
                $response = $this->httpClient->request('GET', self::ENDPOINT, [
                    'headers' => [
                        'X-Api-Key' => $this->apiKey,
                    ],
                    'query' => [
                        'q' => $trendQuery->formattedQuery,
                        'language' => $trendQuery->locale,
                        'from' => $yesterday,
                        'to' => $today,
                        'sortBy' => 'publishedAt',
                        'pageSize' => 20,
                    ],
                ]);

                $this->incrementDailyCounter();
                $data = $response->toArray();

                if (($data['status'] ?? '') !== 'ok') {
                    $this->logger->warning('NewsApiAggregator: non-OK response', [
                        'status' => $data['status'] ?? 'unknown',
                        'message' => $data['message'] ?? '',
                    ]);
                    continue;
                }

                foreach ($data['articles'] ?? [] as $article) {
                    $publishedAt = isset($article['publishedAt'])
                        ? new \DateTimeImmutable($article['publishedAt'])
                        : new \DateTimeImmutable();

                    $results[] = new AggregatorResult(
                        title: $article['title'] ?? '',
                        summary: $article['description'] ?? '',
                        sourceUrl: $article['url'] ?? '',
                        sourceLanguage: $trendQuery->locale,
                        sourceName: $article['source']['name'] ?? 'NewsAPI',
                        publishedAt: $publishedAt,
                        rawContent: $article['content'] ?? $article['description'] ?? '',
                        keywords: $trendQuery->keywords,
                        aggregatorSourceType: AggregatorSourceType::NEWS_API,
                    );
                }

                $this->logger->info('NewsApiAggregator: fetched articles', [
                    'query' => mb_substr($trendQuery->formattedQuery, 0, 60),
                    'count' => \count($data['articles'] ?? []),
                ]);
            } catch (\Throwable $e) {
                $this->logger->warning('NewsApiAggregator: request failed', [
                    'query' => mb_substr($trendQuery->formattedQuery, 0, 60),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    public function getSourceType(): AggregatorSourceType
    {
        return AggregatorSourceType::NEWS_API;
    }

    public function getName(): string
    {
        return 'NewsAPI';
    }

    private function checkDailyLimit(): bool
    {
        $count = $this->getDailyCount();

        if ($count >= self::MAX_DAILY_REQUESTS) {
            return false;
        }

        return true;
    }

    private function getDailyCount(): int
    {
        return $this->cache->get(self::COUNTER_KEY, function (ItemInterface $item): int {
            $item->expiresAfter(self::COUNTER_TTL);

            return 0;
        });
    }

    private function incrementDailyCounter(): void
    {
        $current = $this->getDailyCount();
        $this->cache->delete(self::COUNTER_KEY);
        $this->cache->get(self::COUNTER_KEY, function (ItemInterface $item) use ($current): int {
            $item->expiresAfter(self::COUNTER_TTL);

            return $current + 1;
        });
    }
}
