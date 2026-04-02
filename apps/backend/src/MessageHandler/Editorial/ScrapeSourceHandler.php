<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Message\Editorial\ProcessScrapedArticleMessage;
use App\Message\Editorial\ScrapeSourceMessage;
use App\Service\Scraping\ContentDeduplicator;
use App\Service\Scraping\HtmlToMarkdownConverter;
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
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
        #[Autowire(param: 'scraping.sources')]
        private array $sources,
    ) {}

    public function __invoke(ScrapeSourceMessage $message): void
    {
        if (!isset($this->sources[$message->sourceKey])) {
            $this->logger->error('ScrapeSourceHandler: unknown source', [
                'sourceKey' => $message->sourceKey,
            ]);

            return;
        }

        $source = $this->sources[$message->sourceKey];
        $sourceName = $source['name'];
        $rateLimit = (int) ($source['rate_limit'] ?? 1);

        $this->logger->info('ScrapeSourceHandler: starting', [
            'source' => $sourceName,
            'language' => $message->language ?? 'all',
        ]);

        $feedUrls = $source['feed_urls'] ?? [];
        $totalNew = 0;
        $totalDuplicate = 0;

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
                $scraped = $this->scraper->scrape($feedItem, $rateLimit);
                if ($scraped === null) {
                    continue;
                }

                // Check deduplication
                if ($this->deduplicator->isDuplicate($scraped->bodyText)) {
                    $totalDuplicate++;
                    continue;
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

                $totalNew++;
            }
        }

        $this->logger->info('ScrapeSourceHandler: completed', [
            'source' => $sourceName,
            'new' => $totalNew,
            'duplicates' => $totalDuplicate,
        ]);
    }
}
