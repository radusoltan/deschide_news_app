<?php

declare(strict_types=1);

namespace App\Service\Aggregator\Portal;

use App\Dto\Aggregator\AggregatorResult;
use App\Enum\AggregatorSourceType;
use App\Service\Aggregator\AggregatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Scrapes Corriere della Sera search results.
 *
 * NOTE: Corriere requires JS rendering + cookie consent. This aggregator
 * attempts a direct HTTP fetch with Cookie header. If it consistently
 * returns empty results (403/empty HTML), the BingNewsAggregator with
 * mkt=it-IT serves as an automatic fallback.
 */
#[AutoconfigureTag('app.aggregator')]
final readonly class CorriereAggregator implements AggregatorInterface
{
    private const BASE_URL = 'https://www.corriere.it/ricerca/';
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';
    private const REQUEST_TIMEOUT = 15;

    /**
     * @param list<string> $keywords
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire('%portal.corriere.enabled%')]
        private bool $enabled = true,
        #[Autowire('%portal.corriere.keywords%')]
        private array $keywords = [],
        #[Autowire('%portal.corriere.rate_limit_ms%')]
        private int $rateLimitMs = 300_000,
    ) {}

    public function fetch(): array
    {
        if (!$this->enabled) {
            $this->logger->info('CorriereAggregator: disabled, skipping.');

            return [];
        }

        $results = [];

        foreach ($this->keywords as $keyword) {
            try {
                $keywordResults = $this->searchKeyword($keyword);
                $results = array_merge($results, $keywordResults);
            } catch (\Throwable $e) {
                $this->logger->warning('CorriereAggregator: search failed', [
                    'keyword' => $keyword,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($this->rateLimitMs > 0) {
                usleep($this->rateLimitMs * 1000);
            }
        }

        $this->logger->info('CorriereAggregator: total results', ['count' => \count($results)]);

        return $results;
    }

    public function getSourceType(): AggregatorSourceType
    {
        return AggregatorSourceType::DIRECT_PORTAL;
    }

    public function getName(): string
    {
        return 'Corriere della Sera';
    }

    /**
     * @return AggregatorResult[]
     */
    private function searchKeyword(string $keyword): array
    {
        $url = self::BASE_URL . '?' . http_build_query(['q' => $keyword]);

        $response = $this->httpClient->request('GET', $url, [
            'headers' => [
                'User-Agent' => self::USER_AGENT,
                'Accept-Language' => 'it-IT,it;q=0.9',
                'Accept' => 'text/html,application/xhtml+xml',
                'Cookie' => '_consentCookie=accepted',
            ],
            'timeout' => self::REQUEST_TIMEOUT,
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode === 403 || $statusCode === 429) {
            $this->logger->warning('CorriereAggregator: blocked (JS rendering likely needed)', [
                'statusCode' => $statusCode,
                'keyword' => $keyword,
            ]);

            return [];
        }

        if ($statusCode !== 200) {
            $this->logger->warning('CorriereAggregator: non-200 response', [
                'statusCode' => $statusCode,
            ]);

            return [];
        }

        $html = $response->getContent();

        return $this->parseSearchResults($html, $keyword);
    }

    /**
     * @return AggregatorResult[]
     */
    private function parseSearchResults(string $html, string $keyword): array
    {
        if (mb_strlen($html) < 100) {
            $this->logger->notice('CorriereAggregator: empty HTML — JS rendering required, consider BingNews fallback');

            return [];
        }

        $crawler = new Crawler($html);
        $results = [];

        $crawler->filter('.media-news, .search-result, article')->each(
            function (Crawler $node) use (&$results, $keyword): void {
                $title = '';
                $url = '';
                $snippet = '';

                $titleNode = $node->filter('h3, h2, .title, .media-news__title');
                if ($titleNode->count() > 0) {
                    $title = trim($titleNode->text(''));
                }

                $linkNode = $node->filter('a');
                if ($linkNode->count() > 0) {
                    $href = $linkNode->attr('href') ?? '';
                    if ($href !== '' && !str_starts_with($href, 'http')) {
                        $href = 'https://www.corriere.it' . $href;
                    }
                    $url = $href;
                }

                $descNode = $node->filter('.summary, .description, p');
                if ($descNode->count() > 0) {
                    $snippet = trim($descNode->first()->text(''));
                }

                if ($title !== '' && $url !== '') {
                    $results[] = new AggregatorResult(
                        title: $title,
                        summary: $snippet,
                        sourceUrl: $url,
                        sourceLanguage: 'it',
                        sourceName: 'Corriere della Sera',
                        publishedAt: new \DateTimeImmutable(),
                        rawContent: $snippet,
                        keywords: [$keyword],
                        aggregatorSourceType: AggregatorSourceType::DIRECT_PORTAL,
                    );
                }
            },
        );

        return $results;
    }
}
