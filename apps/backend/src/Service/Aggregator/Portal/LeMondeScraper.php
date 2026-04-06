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
 * Scrapes Le Monde search results for Moldova-related articles.
 *
 * PAYWALL AWARE: Only title + snippet (trailText) are extracted.
 * Full article content is behind paywall and NOT scraped.
 * If blocked (403/captcha), returns empty array gracefully.
 * Fallback: BingNewsAggregator with mkt=fr-FR.
 */
#[AutoconfigureTag('app.aggregator')]
final readonly class LeMondeScraper implements AggregatorInterface
{
    private const BASE_URL = 'https://www.lemonde.fr/recherche/';
    private const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_5) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';
    private const REQUEST_TIMEOUT = 15;

    /**
     * @param list<string> $keywords
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire('%portal.lemonde.enabled%')]
        private bool $enabled = true,
        #[Autowire('%portal.lemonde.keywords%')]
        private array $keywords = [],
        #[Autowire('%portal.lemonde.rate_limit_ms%')]
        private int $rateLimitMs = 300_000,
    ) {}

    public function fetch(): array
    {
        if (!$this->enabled) {
            $this->logger->info('LeMondeScraper: disabled, skipping.');

            return [];
        }

        $results = [];

        foreach ($this->keywords as $keyword) {
            try {
                $keywordResults = $this->searchKeyword($keyword);
                $results = array_merge($results, $keywordResults);
            } catch (\Throwable $e) {
                $this->logger->warning('LeMondeScraper: search failed', [
                    'keyword' => $keyword,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($this->rateLimitMs > 0) {
                usleep($this->rateLimitMs * 1000);
            }
        }

        $this->logger->info('LeMondeScraper: total results', ['count' => \count($results)]);

        return $results;
    }

    public function getSourceType(): AggregatorSourceType
    {
        return AggregatorSourceType::DIRECT_PORTAL;
    }

    public function getName(): string
    {
        return 'Le Monde';
    }

    /**
     * @return AggregatorResult[]
     */
    private function searchKeyword(string $keyword): array
    {
        $url = self::BASE_URL . '?' . http_build_query(['search_keywords' => $keyword]);

        $response = $this->httpClient->request('GET', $url, [
            'headers' => [
                'User-Agent' => self::USER_AGENT,
                'Accept-Language' => 'fr-FR,fr;q=0.9',
                'Accept' => 'text/html,application/xhtml+xml',
            ],
            'timeout' => self::REQUEST_TIMEOUT,
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode === 403 || $statusCode === 429 || $statusCode === 451) {
            $this->logger->warning('LeMondeScraper: blocked (anti-bot or captcha)', [
                'statusCode' => $statusCode,
                'keyword' => $keyword,
            ]);

            return [];
        }

        if ($statusCode !== 200) {
            $this->logger->warning('LeMondeScraper: non-200 response', [
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
            return [];
        }

        $crawler = new Crawler($html);
        $results = [];

        $crawler->filter('.river__item, .teaser, article')->each(
            function (Crawler $node) use (&$results, $keyword): void {
                $title = '';
                $url = '';
                $snippet = '';

                $titleNode = $node->filter('h3, h2, .teaser__title');
                if ($titleNode->count() > 0) {
                    $title = trim($titleNode->text(''));
                }

                $linkNode = $node->filter('a');
                if ($linkNode->count() > 0) {
                    $href = $linkNode->attr('href') ?? '';
                    if ($href !== '' && !str_starts_with($href, 'http')) {
                        $href = 'https://www.lemonde.fr' . $href;
                    }
                    $url = $href;
                }

                $descNode = $node->filter('.teaser__desc, .description, p');
                if ($descNode->count() > 0) {
                    $snippet = trim($descNode->first()->text(''));
                }

                if ($title !== '' && $url !== '') {
                    $results[] = new AggregatorResult(
                        title: $title,
                        summary: $snippet,
                        sourceUrl: $url,
                        sourceLanguage: 'fr',
                        sourceName: 'Le Monde',
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
