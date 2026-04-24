<?php

declare(strict_types=1);

namespace App\Service\Aggregator\Portal;

use App\Dto\Aggregator\AggregatorResult;
use App\Dto\Scraping\FeedItem;
use App\Enum\AggregatorSourceType;
use App\Service\Aggregator\AggregatorInterface;
use App\Service\Scraping\RelevanceFilterService;
use App\Service\Scraping\RssFeedParser;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.aggregator')]
final readonly class AnsaAggregator implements AggregatorInterface
{
    /**
     * @param array<string, string> $feedUrls  category => RSS URL
     */
    public function __construct(
        private RssFeedParser $rssFeedParser,
        private RelevanceFilterService $relevanceFilter,
        private LoggerInterface $logger,
        #[Autowire('%portal.ansa.enabled%')]
        private bool $enabled = true,
        #[Autowire('%portal.ansa.feed_urls%')]
        private array $feedUrls = [],
        #[Autowire('%portal.ansa.rate_limit_ms%')]
        private int $rateLimitMs = 60_000,
    ) {}

    public function fetch(): array
    {
        if (!$this->enabled) {
            $this->logger->info('AnsaAggregator: disabled, skipping.');

            return [];
        }

        $results = [];
        $feedCount = 0;

        foreach ($this->feedUrls as $category => $url) {
            try {
                $feedItems = $this->rssFeedParser->parse($url, 'ANSA', 'it');

                $this->logger->info('AnsaAggregator: parsed feed', [
                    'category' => $category,
                    'items' => \count($feedItems),
                ]);

                foreach ($feedItems as $item) {
                    $relevance = $this->relevanceFilter->evaluate(
                        title: $item->title,
                        bodyText: $item->description ?? '',
                        sourceName: 'ANSA',
                    );

                    if ($relevance->isRelevant) {
                        $results[] = $this->mapToResult($item, $category, $relevance->score);
                    }
                }
            } catch (\Throwable $e) {
                $this->logger->warning('AnsaAggregator: feed failed', [
                    'category' => $category,
                    'url' => $url,
                    'error' => $e->getMessage(),
                ]);
            }

            $feedCount++;
            if ($this->rateLimitMs > 0 && $feedCount < \count($this->feedUrls)) {
                usleep($this->rateLimitMs * 1000);
            }
        }

        $this->logger->info('AnsaAggregator: completed', [
            'feeds' => \count($this->feedUrls),
            'relevant' => \count($results),
        ]);

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

    private function mapToResult(FeedItem $item, string $category, int $relevanceScore): AggregatorResult
    {
        return new AggregatorResult(
            title: $item->title,
            summary: $item->description ?? '',
            sourceUrl: $item->url,
            sourceLanguage: 'it',
            sourceName: 'ANSA',
            publishedAt: $item->publishedAt ?? new \DateTimeImmutable(),
            rawContent: $item->description ?? '',
            keywords: [$category, 'relevance:' . $relevanceScore],
            aggregatorSourceType: AggregatorSourceType::DIRECT_PORTAL,
        );
    }
}
