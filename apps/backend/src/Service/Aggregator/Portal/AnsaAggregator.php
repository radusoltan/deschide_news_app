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

#[AutoconfigureTag('app.aggregator')]
final readonly class AnsaAggregator implements AggregatorInterface
{
    private const BASE_URL = 'https://www.ansa.it/sito/ricerca.shtml';
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/125.0.0.0 Safari/537.36';
    private const REQUEST_TIMEOUT = 15;

    /**
     * @param list<string> $keywords
     */
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire('%portal.ansa.enabled%')]
        private bool $enabled = true,
        #[Autowire('%portal.ansa.keywords%')]
        private array $keywords = [],
        #[Autowire('%portal.ansa.rate_limit_ms%')]
        private int $rateLimitMs = 120_000,
    ) {}

    public function fetch(): array
    {
        if (!$this->enabled) {
            $this->logger->info('AnsaAggregator: disabled, skipping.');

            return [];
        }

        $results = [];

        foreach ($this->keywords as $keyword) {
            try {
                $keywordResults = $this->searchKeyword($keyword);
                $results = array_merge($results, $keywordResults);

                $this->logger->info('AnsaAggregator: fetched for keyword', [
                    'keyword' => $keyword,
                    'count' => \count($keywordResults),
                ]);
            } catch (\Throwable $e) {
                $this->logger->warning('AnsaAggregator: search failed', [
                    'keyword' => $keyword,
                    'error' => $e->getMessage(),
                ]);
            }

            if ($this->rateLimitMs > 0) {
                usleep($this->rateLimitMs * 1000);
            }
        }

        return $results;
    }

    public function getSourceType(): AggregatorSourceType
    {
        return AggregatorSourceType::DIRECT_PORTAL;
    }

    public function getName(): string
    {
        return 'ANSA.it';
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
            ],
            'timeout' => self::REQUEST_TIMEOUT,
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200) {
            $this->logger->warning('AnsaAggregator: non-200 response', [
                'statusCode' => $statusCode,
                'keyword' => $keyword,
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
        $crawler = new Crawler($html);
        $results = [];

        $crawler->filter('.search-result-item, .news-item, article')->each(
            function (Crawler $node) use (&$results, $keyword): void {
                $title = '';
                $url = '';
                $snippet = '';

                // Extract title
                $titleNode = $node->filter('h3, h2, .title');
                if ($titleNode->count() > 0) {
                    $title = trim($titleNode->text(''));
                }

                // Extract URL
                $linkNode = $node->filter('a');
                if ($linkNode->count() > 0) {
                    $href = $linkNode->attr('href') ?? '';
                    // Make absolute URL if relative
                    if ($href !== '' && !str_starts_with($href, 'http')) {
                        $href = 'https://www.ansa.it' . $href;
                    }
                    $url = $href;
                }

                // Extract snippet
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
                        sourceName: 'ANSA',
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
