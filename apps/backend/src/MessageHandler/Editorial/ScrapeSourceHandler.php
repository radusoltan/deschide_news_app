<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Message\Editorial\ProcessScrapedArticleMessage;
use App\Message\Editorial\ScrapeSourceMessage;
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
    public function __construct(
        private RssFeedParser $feedParser,
        private ScraperService $scraper,
        private HtmlToMarkdownConverter $markdownConverter,
        private ContentDeduplicator $deduplicator,
        private RelevanceFilterService $relevanceFilter,
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
        if (!isset($this->sources[$message->sourceKey])) {
            $this->logger->error('ScrapeSourceHandler: unknown source', [
                'sourceKey' => $message->sourceKey,
            ]);

            return ['total' => 0, 'accepted' => 0, 'filtered' => 0, 'duplicates' => 0, 'errors' => 0];
        }

        $source = $this->sources[$message->sourceKey];
        $sourceName = $source['name'];
        $rateLimit = (int) ($source['rate_limit'] ?? 1);
        $useRelevanceFilter = (bool) ($source['relevance_filter'] ?? false);

        $this->logger->info('ScrapeSourceHandler: starting', [
            'source' => $sourceName,
            'language' => $message->language ?? 'all',
            'relevanceFilter' => $useRelevanceFilter,
        ]);

        $feedUrls = $source['feed_urls'] ?? [];
        $stats = ['total' => 0, 'accepted' => 0, 'filtered' => 0, 'duplicates' => 0, 'errors' => 0];

        foreach ($feedUrls as $lang => $feedUrl) {
            // Skip if specific language requested and this isn't it
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

                    // Check deduplication
                    if ($this->deduplicator->isDuplicate($scraped->bodyText)) {
                        $stats['duplicates']++;
                        continue;
                    }

                    // Apply relevance filter for international sources
                    if ($useRelevanceFilter) {
                        $relevance = $this->relevanceFilter->evaluate(
                            $feedItem->title,
                            $scraped->bodyText,
                            $sourceName,
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

                    // Convert HTML to Markdown
                    $bodyMarkdown = $this->markdownConverter->convert($scraped->bodyHtml);

                    // Dispatch for processing
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

        $this->logger->info('Sentinel scraping complet', [
            'source' => $message->sourceKey,
            'total_items' => $stats['total'],
            'accepted' => $stats['accepted'],
            'filtered' => $stats['filtered'],
            'duplicates' => $stats['duplicates'],
            'errors' => $stats['errors'],
        ]);

        return $stats;
    }
}
