<?php

declare(strict_types=1);

namespace App\Service\Aggregator;

use App\Dto\Aggregator\AggregatorResult;
use App\Enum\AggregatorSourceType;
use App\Service\Scraping\RssFeedParser;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Aggregates Facebook public page posts via RSS-Bridge (self-hosted).
 *
 * IMPORTANT: RSS-Bridge Facebook bridges break frequently due to Meta DOM changes.
 * If feeds return empty consistently, consider disabling and using Apify
 * Facebook Pages Scraper with residential proxy as alternative (Plan B).
 */
#[AutoconfigureTag('app.aggregator')]
final readonly class FacebookRssBridgeAggregator implements AggregatorInterface
{
    /**
     * @param list<array{name: string, page_id: string, language: string}> $pages
     */
    public function __construct(
        private RssFeedParser $rssFeedParser,
        private LoggerInterface $logger,
        #[Autowire('%facebook.rss_bridge.enabled%')]
        private bool $enabled,
        #[Autowire('%facebook.rss_bridge.base_url%')]
        private string $baseUrl,
        #[Autowire('%facebook.rss_bridge.pages%')]
        private array $pages,
        #[Autowire('%facebook.rss_bridge.rate_limit_seconds%')]
        private int $rateLimitSeconds,
    ) {}

    public function fetch(): array
    {
        if (!$this->enabled) {
            $this->logger->info('FacebookRssBridgeAggregator: disabled, skipping.');

            return [];
        }

        if ($this->baseUrl === '') {
            $this->logger->info('FacebookRssBridgeAggregator: no bridge URL configured, skipping.');

            return [];
        }

        $results = [];

        foreach ($this->pages as $page) {
            try {
                $pageResults = $this->fetchPage($page);
                $results = array_merge($results, $pageResults);

                $this->logger->info('FacebookRssBridgeAggregator: fetched from page', [
                    'page' => $page['name'],
                    'count' => \count($pageResults),
                ]);
            } catch (\Throwable $e) {
                // Graceful fallback: log warning, do NOT throw
                $this->logger->warning('FacebookRssBridgeAggregator: page fetch failed', [
                    'page' => $page['name'],
                    'page_id' => $page['page_id'],
                    'error' => $e->getMessage(),
                ]);
            }

            // Heavy rate limiting — Facebook blocks aggressively
            if ($this->rateLimitSeconds > 0) {
                sleep($this->rateLimitSeconds);
            }
        }

        $this->logger->info('FacebookRssBridgeAggregator: total results', [
            'total' => \count($results),
            'pages' => \count($this->pages),
        ]);

        return $results;
    }

    public function getSourceType(): AggregatorSourceType
    {
        return AggregatorSourceType::FACEBOOK_RSS;
    }

    public function getName(): string
    {
        return 'Facebook RSS Bridge';
    }

    /**
     * @param array{name: string, page_id: string, language: string} $page
     *
     * @return AggregatorResult[]
     */
    private function fetchPage(array $page): array
    {
        $feedUrl = sprintf(
            '%s/?action=display&bridge=FacebookBridge&context=User&u=%s&format=Atom',
            rtrim($this->baseUrl, '/'),
            urlencode($page['page_id']),
        );

        $feedItems = $this->rssFeedParser->parse(
            feedUrl: $feedUrl,
            sourceName: $page['name'],
            language: $page['language'],
        );

        // Empty feed may indicate RSS-Bridge breakage due to Meta DOM changes
        if ($feedItems === []) {
            $this->logger->notice('FacebookRssBridgeAggregator: empty feed — bridge may be broken', [
                'page' => $page['name'],
                'url' => $feedUrl,
            ]);
        }

        $results = [];

        foreach ($feedItems as $feedItem) {
            $results[] = new AggregatorResult(
                title: $feedItem->title,
                summary: $feedItem->description ?? '',
                sourceUrl: $feedItem->url,
                sourceLanguage: $page['language'],
                sourceName: $page['name'],
                publishedAt: $feedItem->publishedAt ?? new \DateTimeImmutable(),
                rawContent: $feedItem->description ?? '',
                keywords: [],
                aggregatorSourceType: AggregatorSourceType::FACEBOOK_RSS,
            );
        }

        return $results;
    }
}
