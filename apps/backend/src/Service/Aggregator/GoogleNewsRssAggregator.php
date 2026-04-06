<?php

declare(strict_types=1);

namespace App\Service\Aggregator;

use App\Dto\Aggregator\AggregatorResult;
use App\Dto\Scraping\FeedItem;
use App\Enum\AggregatorSourceType;
use App\Service\Scraping\RssFeedParser;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.aggregator')]
final readonly class GoogleNewsRssAggregator implements AggregatorInterface
{
    private const BASE_URL = 'https://news.google.com/rss/search';

    /**
     * @param array<string, list<string>>                                 $keywords
     * @param array<string, array{hl: string, gl: string, ceid: string}> $locales
     * @param list<string>                                                $userAgents
     */
    public function __construct(
        private RssFeedParser $rssFeedParser,
        private AggregatorRateLimiter $rateLimiter,
        private LoggerInterface $logger,
        private array $keywords,
        private array $locales,
        private array $userAgents,
        private bool $enabled = true,
    ) {}

    public function fetch(): array
    {
        if (!$this->enabled) {
            $this->logger->info('GoogleNewsRssAggregator: disabled, skipping.');

            return [];
        }

        if (!$this->rateLimiter->isAllowed('google_news_rss')) {
            $this->logger->warning('GoogleNewsRssAggregator: rate limit exceeded, skipping.');

            return [];
        }

        $results = [];

        foreach ($this->keywords as $locale => $keywordList) {
            $localeConfig = $this->locales[$locale] ?? null;
            if ($localeConfig === null) {
                continue;
            }

            foreach ($keywordList as $keyword) {
                $url = $this->buildUrl($keyword, $localeConfig);

                $this->logger->info('GoogleNewsRssAggregator: fetching', [
                    'locale' => $locale,
                    'keyword' => $keyword,
                    'url' => $url,
                ]);

                $feedItems = $this->rssFeedParser->parse($url, 'Google News', $locale);

                foreach ($feedItems as $feedItem) {
                    $results[] = $this->mapToResult($feedItem, $keyword);
                }
            }
        }

        $this->logger->info('GoogleNewsRssAggregator: fetched {count} results', [
            'count' => \count($results),
        ]);

        return $results;
    }

    public function getSourceType(): AggregatorSourceType
    {
        return AggregatorSourceType::GOOGLE_NEWS_RSS;
    }

    public function getName(): string
    {
        return 'Google News RSS';
    }

    /**
     * @param array{hl: string, gl: string, ceid: string} $localeConfig
     */
    private function buildUrl(string $keyword, array $localeConfig): string
    {
        return self::BASE_URL . '?' . http_build_query([
            'q' => $keyword,
            'hl' => $localeConfig['hl'],
            'gl' => $localeConfig['gl'],
            'ceid' => $localeConfig['ceid'],
        ]);
    }

    private function pickUserAgent(): string
    {
        if ($this->userAgents === []) {
            return 'DeschideNewsBot/1.0';
        }

        return $this->userAgents[array_rand($this->userAgents)];
    }

    private function mapToResult(FeedItem $feedItem, string $keyword): AggregatorResult
    {
        return new AggregatorResult(
            title: $feedItem->title,
            summary: $feedItem->description ?? '',
            sourceUrl: $feedItem->url,
            sourceLanguage: $feedItem->language,
            sourceName: $feedItem->sourceName,
            publishedAt: $feedItem->publishedAt ?? new \DateTimeImmutable(),
            rawContent: $feedItem->description ?? '',
            keywords: [$keyword],
            aggregatorSourceType: AggregatorSourceType::GOOGLE_NEWS_RSS,
        );
    }
}
