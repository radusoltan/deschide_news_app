<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Message\Editorial\ProcessScrapedArticleMessage;
use App\Message\Editorial\ScrapeSourceMessage;
use App\Service\Aggregator\TrendQueryGeneratorService;
use App\Service\Scraping\ContentDeduplicator;
use App\Service\Scraping\HtmlToMarkdownConverter;
use App\Service\Scraping\RelevanceFilterService;
use App\Service\Scraping\RssFeedParser;
use App\Service\Scraping\ScraperService;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class ScrapeSourceHandler
{
    private const PRIORITY_GROUPS = [
        'local' => [5],            // Priority 5: local sources (30 min)
        'international_high' => [3], // Priority 3: Reuters, AP, UNIAN (2h)
        'international_medium' => [4], // Priority 4: Agerpres, EC, Consilium, Europarl (4h)
    ];

    public function __construct(
        private RssFeedParser $feedParser,
        private ScraperService $scraper,
        private HtmlToMarkdownConverter $markdownConverter,
        private ContentDeduplicator $deduplicator,
        private RelevanceFilterService $relevanceFilter,
        private TrendQueryGeneratorService $trendQueryGenerator,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
        #[Autowire(param: 'scraping.sources')]
        private array $sources,
    ) {}

    /**
     * @return array{total: int, accepted: int, filtered: int, duplicates: int, errors: int}
     */
    public function __invoke(ScrapeSourceMessage $message): array
    {
        // Determine which sources to scrape
        $sourcesToProcess = $this->resolveSources($message);

        if (empty($sourcesToProcess)) {
            $this->logger->warning('ScrapeSourceHandler: no sources matched', [
                'sourceKey' => $message->sourceKey,
                'priorityGroup' => $message->priorityGroup,
            ]);

            return ['total' => 0, 'accepted' => 0, 'filtered' => 0, 'duplicates' => 0, 'errors' => 0];
        }

        // Load dynamic keywords from trending topics
        $dynamicKeywords = $this->loadDynamicKeywords();

        $aggregateStats = ['total' => 0, 'accepted' => 0, 'filtered' => 0, 'duplicates' => 0, 'errors' => 0];

        foreach ($sourcesToProcess as $sourceKey => $source) {
            $stats = $this->processSource($sourceKey, $source, $message, $dynamicKeywords);

            foreach ($stats as $key => $value) {
                $aggregateStats[$key] += $value;
            }
        }

        $this->logger->info('Sentinel scraping complet', [
            'priorityGroup' => $message->priorityGroup,
            'sourceKey' => $message->sourceKey,
            'sourcesProcessed' => \count($sourcesToProcess),
            'total_items' => $aggregateStats['total'],
            'accepted' => $aggregateStats['accepted'],
            'filtered' => $aggregateStats['filtered'],
            'duplicates' => $aggregateStats['duplicates'],
            'errors' => $aggregateStats['errors'],
            'dynamicKeywords' => \count($dynamicKeywords),
        ]);

        return $aggregateStats;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function resolveSources(ScrapeSourceMessage $message): array
    {
        // Single source mode
        if ($message->sourceKey !== null) {
            if (!isset($this->sources[$message->sourceKey])) {
                $this->logger->error('ScrapeSourceHandler: unknown source', ['sourceKey' => $message->sourceKey]);

                return [];
            }

            return [$message->sourceKey => $this->sources[$message->sourceKey]];
        }

        // Priority group mode
        if ($message->priorityGroup !== null) {
            $allowedWeights = self::PRIORITY_GROUPS[$message->priorityGroup] ?? [];

            if (empty($allowedWeights)) {
                $this->logger->error('ScrapeSourceHandler: unknown priority group', [
                    'priorityGroup' => $message->priorityGroup,
                ]);

                return [];
            }

            return array_filter(
                $this->sources,
                fn (array $s) => \in_array((int) ($s['priority_weight'] ?? 0), $allowedWeights, true),
            );
        }

        return $this->sources;
    }

    /**
     * @return string[]
     */
    private function loadDynamicKeywords(): array
    {
        try {
            return $this->trendQueryGenerator->getRelevanceKeywords();
        } catch (\Throwable $e) {
            $this->logger->warning('ScrapeSourceHandler: failed to load dynamic keywords', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param array<string, mixed> $source
     * @param string[] $dynamicKeywords
     *
     * @return array{total: int, accepted: int, filtered: int, duplicates: int, errors: int}
     */
    private function processSource(string $sourceKey, array $source, ScrapeSourceMessage $message, array $dynamicKeywords): array
    {
        $sourceName = $source['name'];
        $rateLimit = (int) ($source['rate_limit'] ?? 1);
        $useRelevanceFilter = (bool) ($source['relevance_filter'] ?? false);

        $this->logger->info('ScrapeSourceHandler: starting source', [
            'source' => $sourceName,
            'language' => $message->language ?? 'all',
            'relevanceFilter' => $useRelevanceFilter,
        ]);

        $feedUrls = $source['feed_urls'] ?? [];
        $stats = ['total' => 0, 'accepted' => 0, 'filtered' => 0, 'duplicates' => 0, 'errors' => 0];

        foreach ($feedUrls as $lang => $feedUrl) {
            if ($message->language !== null && $message->language !== $lang) {
                continue;
            }

            $items = $this->feedParser->parse($feedUrl, $sourceName, $lang, $message->limit);

            $this->logger->info('ScrapeSourceHandler: feed parsed', [
                'source' => $sourceName,
                'language' => $lang,
                'items' => \count($items),
            ]);

            foreach ($items as $feedItem) {
                $stats['total']++;

                try {
                    $scraped = $this->scraper->scrape($feedItem, $rateLimit);
                    if ($scraped === null) {
                        $stats['errors']++;
                        continue;
                    }

                    if ($this->deduplicator->isDuplicate($scraped->bodyText)) {
                        $stats['duplicates']++;
                        continue;
                    }

                    if ($useRelevanceFilter) {
                        $relevance = $this->relevanceFilter->evaluate(
                            $feedItem->title,
                            $scraped->bodyText,
                            $sourceName,
                            $dynamicKeywords,
                        );

                        if (!$relevance->isRelevant) {
                            $this->logger->info('Sentinel: articol FILTRAT (irelevant)', [
                                'title' => mb_substr($feedItem->title, 0, 100),
                                'source' => $sourceName,
                                'score' => $relevance->score,
                            ]);
                            $stats['filtered']++;
                            continue;
                        }

                        $this->logger->info('Sentinel: articol ACCEPTAT', [
                            'title' => mb_substr($feedItem->title, 0, 100),
                            'source' => $sourceName,
                            'score' => $relevance->score,
                            'tier1' => $relevance->tier1Count,
                            'matches' => \count($relevance->matches),
                        ]);
                    }

                    $contentHash = $this->deduplicator->computeHash($scraped->bodyText);
                    $bodyMarkdown = $this->markdownConverter->convert($scraped->bodyHtml);

                    $this->messageBus->dispatch(new ProcessScrapedArticleMessage(
                        title: $scraped->title,
                        bodyMarkdown: $bodyMarkdown,
                        sourceUrl: $scraped->url,
                        sourceName: $scraped->sourceName,
                        originalLanguage: $scraped->language,
                        contentHash: $contentHash,
                        publishedAt: $scraped->publishedAt,
                    ));

                    $stats['accepted']++;
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    $this->logger->warning('ScrapeSourceHandler: item error', [
                        'source' => $sourceName,
                        'url' => $feedItem->url,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $stats;
    }
}
