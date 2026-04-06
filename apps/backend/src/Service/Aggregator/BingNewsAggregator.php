<?php

declare(strict_types=1);

namespace App\Service\Aggregator;

use App\Dto\Aggregator\AggregatorResult;
use App\Enum\AggregatorSourceType;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AutoconfigureTag('app.aggregator')]
final readonly class BingNewsAggregator implements AggregatorInterface
{
    private const ENDPOINT = 'https://api.bing.microsoft.com/v7.0/news/search';
    private const THROTTLE_MS = 350; // 3 req/sec tier

    private const MARKET_MAP = [
        'it' => 'it-IT',
        'de' => 'de-DE',
        'fr' => 'fr-FR',
        'es' => 'es-ES',
        'pt' => 'pt-PT',
        'en' => 'en-GB',
        'ro' => 'ro-RO',
        'ru' => 'ru-RU',
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private TrendQueryGeneratorService $trendQueryGenerator,
        private LoggerInterface $logger,
        #[Autowire(env: 'BING_NEWS_KEY')]
        private string $apiKey = '',
    ) {}

    public function fetch(): array
    {
        if ($this->apiKey === '') {
            $this->logger->info('BingNewsAggregator: no API key configured, skipping.');

            return [];
        }

        $queries = $this->trendQueryGenerator->getCachedQueries();
        $bingQueries = array_filter(
            $queries,
            fn ($q) => $q->aggregatorType === AggregatorSourceType::BING_NEWS,
        );

        if (empty($bingQueries)) {
            $this->logger->info('BingNewsAggregator: no trend queries for BING_NEWS.');

            return [];
        }

        $results = [];

        foreach ($bingQueries as $trendQuery) {
            $market = self::MARKET_MAP[$trendQuery->locale] ?? 'en-GB';

            try {
                $response = $this->httpClient->request('GET', self::ENDPOINT, [
                    'headers' => [
                        'Ocp-Apim-Subscription-Key' => $this->apiKey,
                    ],
                    'query' => [
                        'q' => $trendQuery->formattedQuery,
                        'mkt' => $market,
                        'count' => 20,
                        'freshness' => 'Week',
                    ],
                ]);

                $data = $response->toArray();

                foreach ($data['value'] ?? [] as $article) {
                    $publishedAt = isset($article['datePublished'])
                        ? new \DateTimeImmutable($article['datePublished'])
                        : new \DateTimeImmutable();

                    $providerName = $article['provider'][0]['name'] ?? 'Bing News';

                    $results[] = new AggregatorResult(
                        title: $article['name'] ?? '',
                        summary: $article['description'] ?? '',
                        sourceUrl: $article['url'] ?? '',
                        sourceLanguage: $trendQuery->locale,
                        sourceName: $providerName,
                        publishedAt: $publishedAt,
                        rawContent: $article['description'] ?? '',
                        keywords: $trendQuery->keywords,
                        aggregatorSourceType: AggregatorSourceType::BING_NEWS,
                    );
                }

                $this->logger->info('BingNewsAggregator: fetched articles', [
                    'query' => mb_substr($trendQuery->formattedQuery, 0, 60),
                    'market' => $market,
                    'count' => \count($data['value'] ?? []),
                ]);

                // Throttle between requests
                usleep(self::THROTTLE_MS * 1000);
            } catch (\Throwable $e) {
                $this->logger->warning('BingNewsAggregator: request failed', [
                    'query' => mb_substr($trendQuery->formattedQuery, 0, 60),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results;
    }

    public function getSourceType(): AggregatorSourceType
    {
        return AggregatorSourceType::BING_NEWS;
    }

    public function getName(): string
    {
        return 'Bing News';
    }
}
