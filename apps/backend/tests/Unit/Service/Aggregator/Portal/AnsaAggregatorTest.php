<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Aggregator\Portal;

use App\Dto\Aggregator\AggregatorResult;
use App\Enum\AggregatorSourceType;
use App\Service\Aggregator\Portal\AnsaAggregator;
use App\Service\Scraping\RelevanceFilterService;
use App\Service\Scraping\RssFeedParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(AnsaAggregator::class)]
class AnsaAggregatorTest extends TestCase
{
    private const FEED_URLS = [
        'mondo' => 'https://www.ansa.it/sito/notizie/mondo/mondo_rss.xml',
        'topnews' => 'https://www.ansa.it/sito/notizie/topnews/topnews_rss.xml',
    ];

    /** RSS XML with one Moldova-relevant and one irrelevant item. */
    private const RSS_WITH_RELEVANT = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>ANSA Mondo</title>
    <item>
      <title>Moldova firma accordo con UE per integrazione europea</title>
      <link>https://www.ansa.it/mondo/moldova-eu.html</link>
      <description>Il governo moldavo ha firmato un nuovo accordo di associazione.</description>
      <pubDate>Mon, 06 Apr 2026 10:00:00 +0200</pubDate>
    </item>
    <item>
      <title>Terremoto in Giappone, scossa di magnitudo 6.2</title>
      <link>https://www.ansa.it/mondo/giappone.html</link>
      <description>Forte scossa registrata nel Pacifico senza danni.</description>
      <pubDate>Mon, 06 Apr 2026 09:00:00 +0200</pubDate>
    </item>
  </channel>
</rss>
XML;

    private const RSS_EMPTY = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>ANSA</title>
  </channel>
</rss>
XML;

    public function testFetchReturnsOnlyRelevantResults(): void
    {
        $aggregator = $this->createAggregator(
            rssXml: self::RSS_WITH_RELEVANT,
            feedUrls: ['mondo' => 'https://www.ansa.it/mondo_rss.xml'],
        );

        $results = $aggregator->fetch();

        // Only "Moldova" article passes the relevance filter
        self::assertCount(1, $results);
        self::assertInstanceOf(AggregatorResult::class, $results[0]);
        self::assertStringContainsString('Moldova', $results[0]->title);
        self::assertSame('ANSA', $results[0]->sourceName);
        self::assertSame('it', $results[0]->sourceLanguage);
        self::assertSame(AggregatorSourceType::DIRECT_PORTAL, $results[0]->aggregatorSourceType);
    }

    public function testFetchQueriesAllFeeds(): void
    {
        $requestCount = 0;
        $httpClient = new MockHttpClient(function () use (&$requestCount): MockResponse {
            $requestCount++;

            return new MockResponse(self::RSS_EMPTY);
        });

        $aggregator = new AnsaAggregator(
            rssFeedParser: new RssFeedParser($httpClient, new NullLogger(), 'TestBot/1.0', 30),
            relevanceFilter: $this->createRelevanceFilter(),
            logger: new NullLogger(),
            enabled: true,
            feedUrls: self::FEED_URLS,
            rateLimitMs: 0,
        );

        $aggregator->fetch();

        self::assertSame(2, $requestCount);
    }

    public function testFetchReturnsEmptyWhenDisabled(): void
    {
        $httpClient = new MockHttpClient(function (): MockResponse {
            self::fail('Should not make HTTP requests when disabled');
        });

        $aggregator = new AnsaAggregator(
            rssFeedParser: new RssFeedParser($httpClient, new NullLogger(), 'TestBot/1.0', 30),
            relevanceFilter: $this->createRelevanceFilter(),
            logger: new NullLogger(),
            enabled: false,
            feedUrls: self::FEED_URLS,
            rateLimitMs: 0,
        );

        self::assertSame([], $aggregator->fetch());
    }

    public function testFetchFiltersOutIrrelevantArticles(): void
    {
        $rssNoMoldova = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>ANSA</title>
    <item>
      <title>Calcio: Serie A, risultati della giornata</title>
      <link>https://www.ansa.it/sport/calcio.html</link>
      <description>Risultati del campionato italiano di calcio.</description>
      <pubDate>Mon, 06 Apr 2026 08:00:00 +0200</pubDate>
    </item>
  </channel>
</rss>
XML;

        $aggregator = $this->createAggregator(
            rssXml: $rssNoMoldova,
            feedUrls: ['topnews' => 'https://www.ansa.it/topnews_rss.xml'],
        );

        self::assertSame([], $aggregator->fetch());
    }

    public function testFetchHandlesFeedError(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('', ['http_code' => 500]));

        $aggregator = new AnsaAggregator(
            rssFeedParser: new RssFeedParser($httpClient, new NullLogger(), 'TestBot/1.0', 30),
            relevanceFilter: $this->createRelevanceFilter(),
            logger: new NullLogger(),
            enabled: true,
            feedUrls: ['mondo' => 'https://www.ansa.it/mondo_rss.xml'],
            rateLimitMs: 0,
        );

        // Should not throw, just return empty
        self::assertSame([], $aggregator->fetch());
    }

    public function testGetSourceType(): void
    {
        $aggregator = $this->createDisabledAggregator();

        self::assertSame(AggregatorSourceType::DIRECT_PORTAL, $aggregator->getSourceType());
    }

    public function testGetName(): void
    {
        $aggregator = $this->createDisabledAggregator();

        self::assertSame('ANSA.it', $aggregator->getName());
    }

    public function testResultContainsCategoryKeyword(): void
    {
        $aggregator = $this->createAggregator(
            rssXml: self::RSS_WITH_RELEVANT,
            feedUrls: ['mondo' => 'https://www.ansa.it/mondo_rss.xml'],
        );

        $results = $aggregator->fetch();

        self::assertCount(1, $results);
        self::assertContains('mondo', $results[0]->keywords);
    }

    public function testResultPreservesPublishedAt(): void
    {
        $aggregator = $this->createAggregator(
            rssXml: self::RSS_WITH_RELEVANT,
            feedUrls: ['mondo' => 'https://www.ansa.it/mondo_rss.xml'],
        );

        $results = $aggregator->fetch();

        self::assertCount(1, $results);
        self::assertSame('2026-04-06', $results[0]->publishedAt->format('Y-m-d'));
    }

    private function createAggregator(string $rssXml, array $feedUrls): AnsaAggregator
    {
        $httpClient = new MockHttpClient(new MockResponse($rssXml));

        return new AnsaAggregator(
            rssFeedParser: new RssFeedParser($httpClient, new NullLogger(), 'TestBot/1.0', 30),
            relevanceFilter: $this->createRelevanceFilter(),
            logger: new NullLogger(),
            enabled: true,
            feedUrls: $feedUrls,
            rateLimitMs: 0,
        );
    }

    private function createDisabledAggregator(): AnsaAggregator
    {
        $httpClient = new MockHttpClient(new MockResponse(''));

        return new AnsaAggregator(
            rssFeedParser: new RssFeedParser($httpClient, new NullLogger(), 'TestBot/1.0', 30),
            relevanceFilter: $this->createRelevanceFilter(),
            logger: new NullLogger(),
            enabled: false,
            feedUrls: [],
            rateLimitMs: 0,
        );
    }

    private function createRelevanceFilter(): RelevanceFilterService
    {
        return new RelevanceFilterService(
            tier1Keywords: ['Moldova', 'moldavo', 'moldava', 'moldavi'],
            tier2Keywords: ['Romania', 'Ucraina', 'Transnistria'],
            tier3Keywords: ['Chișinău', 'Sandu'],
            minScore: 3,
            logger: new NullLogger(),
        );
    }
}
