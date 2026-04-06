<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Scraping;

use App\Dto\Scraping\FeedItem;
use App\Dto\Scraping\ScrapedContent;
use App\Message\Editorial\ScrapeSourceMessage;
use App\MessageHandler\Editorial\ScrapeSourceHandler;
use App\Repository\ArticleRepository;
use App\Service\Aggregator\TrendQueryGeneratorService;
use App\Service\Scraping\ContentDeduplicator;
use App\Service\Scraping\HtmlToMarkdownConverter;
use App\Service\Scraping\RelevanceFilterService;
use App\Service\Scraping\RssFeedParser;
use App\Service\Scraping\ScraperService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Tests the relevance filter integration in ScrapeSourceHandler.
 *
 * Uses real final service instances with mock HttpClient/Repository
 * to test the full filtering pipeline.
 */
class ScrapeSourceHandlerFilterTest extends TestCase
{
    #[Test]
    public function internationalSourceRelevantArticlePasses(): void
    {
        $dispatched = [];
        $handler = $this->buildHandler(
            feedTitle: 'Moldova signs new EU deal',
            articleBody: 'The Republic of Moldova signed a major agreement with the European Union today in Chișinău.',
            duplicateExists: false,
            dispatched: $dispatched,
            useLocalSource: false,
        );

        $stats = $handler(new ScrapeSourceMessage('reuters_world', limit: 1));

        $this->assertSame(1, $stats['total']);
        $this->assertSame(1, $stats['accepted']);
        $this->assertSame(0, $stats['filtered']);
    }

    #[Test]
    public function internationalSourceIrrelevantArticleIsFiltered(): void
    {
        $dispatched = [];
        $handler = $this->buildHandler(
            feedTitle: 'Heavy snowfall expected in Kansas',
            articleBody: 'The National Weather Service issued a winter storm warning for Kansas and Nebraska today.',
            duplicateExists: false,
            dispatched: $dispatched,
            useLocalSource: false,
        );

        $stats = $handler(new ScrapeSourceMessage('reuters_world', limit: 1));

        $this->assertSame(1, $stats['total']);
        $this->assertSame(0, $stats['accepted']);
        $this->assertSame(1, $stats['filtered']);
        $this->assertCount(0, $dispatched);
    }

    #[Test]
    public function localSourceBypassesRelevanceFilter(): void
    {
        $dispatched = [];
        $handler = $this->buildHandler(
            feedTitle: 'Sedinta guvernului despre buget',
            articleBody: 'Guvernul a discutat bugetul pe anul 2026. Economia creste constant.',
            duplicateExists: false,
            dispatched: $dispatched,
            useLocalSource: true,
        );

        $stats = $handler(new ScrapeSourceMessage('moldpres', limit: 1));

        $this->assertSame(1, $stats['total']);
        $this->assertSame(1, $stats['accepted']);
        $this->assertSame(0, $stats['filtered']);
        $this->assertCount(1, $dispatched);
    }

    #[Test]
    public function duplicateArticleIsCountedCorrectly(): void
    {
        $dispatched = [];
        $handler = $this->buildHandler(
            feedTitle: 'Moldova news update today',
            articleBody: 'News about Moldova and its progress toward EU accession today.',
            duplicateExists: true,
            dispatched: $dispatched,
            useLocalSource: false,
        );

        $stats = $handler(new ScrapeSourceMessage('reuters_world', limit: 1));

        $this->assertSame(1, $stats['total']);
        $this->assertSame(0, $stats['accepted']);
        $this->assertSame(1, $stats['duplicates']);
        $this->assertCount(0, $dispatched);
    }

    /**
     * @param list<object> $dispatched
     */
    private function buildHandler(
        string $feedTitle,
        string $articleBody,
        bool $duplicateExists,
        array &$dispatched,
        bool $useLocalSource,
    ): ScrapeSourceHandler {
        $logger = new NullLogger();

        // Build RSS XML with the test item
        $rssXml = sprintf(
            '<?xml version="1.0" encoding="UTF-8"?><rss version="2.0"><channel><item><title>%s</title><link>https://example.com/test-article</link><description>%s</description><pubDate>%s</pubDate></item></channel></rss>',
            htmlspecialchars($feedTitle, \ENT_XML1),
            htmlspecialchars(mb_substr($articleBody, 0, 100), \ENT_XML1),
            date('r'),
        );

        // Build article HTML
        $articleHtml = sprintf('<html><body><article><p>%s</p></article></body></html>', htmlspecialchars($articleBody));

        // FeedParser HttpClient — returns RSS XML
        $feedClient = $this->createMock(HttpClientInterface::class);
        $feedResponse = $this->createMock(ResponseInterface::class);
        $feedResponse->method('getContent')->willReturn($rssXml);
        $feedResponse->method('getStatusCode')->willReturn(200);
        $feedClient->method('request')->willReturn($feedResponse);

        $feedParser = new RssFeedParser($feedClient, $logger, 'TestBot/1.0', 30);

        // Scraper HttpClient — returns article HTML
        $scraperClient = $this->createMock(HttpClientInterface::class);
        $scraperResponse = $this->createMock(ResponseInterface::class);
        $scraperResponse->method('getContent')->willReturn($articleHtml);
        $scraperResponse->method('getStatusCode')->willReturn(200);
        $scraperClient->method('request')->willReturn($scraperResponse);

        $scraper = new ScraperService($scraperClient, $logger, 'TestBot/1.0', 30, 1000);

        $markdownConverter = new HtmlToMarkdownConverter();

        // ContentDeduplicator — uses mock repository
        $articleRepo = $this->createMock(ArticleRepository::class);
        if ($duplicateExists) {
            $mock = $this->createMock(\App\Entity\Article::class);
            $articleRepo->method('findOneBy')->willReturn($mock);
        } else {
            $articleRepo->method('findOneBy')->willReturn(null);
        }
        $deduplicator = new ContentDeduplicator($articleRepo);

        // Real RelevanceFilterService with keywords
        $relevanceFilter = new RelevanceFilterService(
            tier1Keywords: ['Moldova', 'Moldovan', 'Chișinău', 'Chisinau', 'Sandu', 'Молдова', 'Transnistria'],
            tier2Keywords: ['Eastern Europe', 'Black Sea', 'EU enlargement'],
            tier3Keywords: ['Maia Sandu', 'Igor Dodon'],
            minScore: 1,
            logger: $logger,
        );

        // Message bus — collects dispatched messages
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function ($msg) use (&$dispatched) {
            $dispatched[] = $msg;

            return new Envelope($msg);
        });

        $sourceKey = $useLocalSource ? 'moldpres' : 'reuters_world';
        $sources = $useLocalSource
            ? [
                'moldpres' => [
                    'name' => 'Moldpres',
                    'feed_urls' => ['ro' => 'https://moldpres.md/rss'],
                    'rate_limit' => 1000,
                    'is_international' => false,
                    'relevance_filter' => false,
                ],
            ]
            : [
                'reuters_world' => [
                    'name' => 'Reuters',
                    'feed_urls' => ['en' => 'https://reuters.com/rss'],
                    'rate_limit' => 1000,
                    'is_international' => true,
                    'relevance_filter' => true,
                ],
            ];

        $trendQueryGenerator = $this->createMock(TrendQueryGeneratorService::class);
        $trendQueryGenerator->method('getRelevanceKeywords')->willReturn([]);

        return new ScrapeSourceHandler(
            feedParser: $feedParser,
            scraper: $scraper,
            markdownConverter: $markdownConverter,
            deduplicator: $deduplicator,
            relevanceFilter: $relevanceFilter,
            trendQueryGenerator: $trendQueryGenerator,
            messageBus: $bus,
            logger: $logger,
            sources: $sources,
        );
    }
}
