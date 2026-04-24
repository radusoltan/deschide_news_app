<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Aggregator\Portal;

use App\Dto\Aggregator\AggregatorResult;
use App\Enum\AggregatorSourceType;
use App\Service\Aggregator\Portal\CorriereAggregator;
use App\Service\Scraping\RelevanceFilterService;
use App\Service\Scraping\RssFeedParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(CorriereAggregator::class)]
class CorriereAggregatorTest extends TestCase
{
    private const FEED_URLS = [
        'esteri' => 'https://www.corriere.it/dynamic-feed/rss/section/Esteri.xml',
        'homepage' => 'https://xml2.corriereobjects.it/feed-hp/homepage.xml',
    ];

    private const RSS_WITH_RELEVANT = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Corriere della Sera - Esteri</title>
    <item>
      <title>Moldova, il parlamento approva la riforma europea</title>
      <link>https://www.corriere.it/esteri/moldova-riforma.shtml</link>
      <description>Il parlamento moldavo vota a favore della riforma di integrazione europea.</description>
      <pubDate>Mon, 06 Apr 2026 10:00:00 +0200</pubDate>
    </item>
    <item>
      <title>Francia, manifestazioni a Parigi contro la riforma pensioni</title>
      <link>https://www.corriere.it/esteri/francia-pensioni.shtml</link>
      <description>Decine di migliaia in piazza nella capitale francese.</description>
      <pubDate>Mon, 06 Apr 2026 09:00:00 +0200</pubDate>
    </item>
  </channel>
</rss>
XML;

    private const RSS_EMPTY = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Corriere della Sera</title>
  </channel>
</rss>
XML;

    public function testFetchReturnsOnlyRelevantResults(): void
    {
        $aggregator = $this->createAggregator(
            rssXml: self::RSS_WITH_RELEVANT,
            feedUrls: ['esteri' => 'https://www.corriere.it/esteri.xml'],
        );

        $results = $aggregator->fetch();

        self::assertCount(1, $results);
        self::assertInstanceOf(AggregatorResult::class, $results[0]);
        self::assertStringContainsString('Moldova', $results[0]->title);
        self::assertSame('Corriere della Sera', $results[0]->sourceName);
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

        $aggregator = new CorriereAggregator(
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

        $aggregator = new CorriereAggregator(
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
    <title>Corriere della Sera</title>
    <item>
      <title>Serie A, il Milan vince il derby</title>
      <link>https://www.corriere.it/sport/milan-derby.shtml</link>
      <description>Il Milan batte l'Inter 2-1 nel derby di Milano.</description>
      <pubDate>Mon, 06 Apr 2026 20:00:00 +0200</pubDate>
    </item>
  </channel>
</rss>
XML;

        $aggregator = $this->createAggregator(
            rssXml: $rssNoMoldova,
            feedUrls: ['homepage' => 'https://www.corriere.it/homepage.xml'],
        );

        self::assertSame([], $aggregator->fetch());
    }

    public function testFetchHandlesFeedError(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('', ['http_code' => 500]));

        $aggregator = new CorriereAggregator(
            rssFeedParser: new RssFeedParser($httpClient, new NullLogger(), 'TestBot/1.0', 30),
            relevanceFilter: $this->createRelevanceFilter(),
            logger: new NullLogger(),
            enabled: true,
            feedUrls: ['esteri' => 'https://www.corriere.it/esteri.xml'],
            rateLimitMs: 0,
        );

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

        self::assertSame('Corriere della Sera', $aggregator->getName());
    }

    public function testResultContainsCategoryKeyword(): void
    {
        $aggregator = $this->createAggregator(
            rssXml: self::RSS_WITH_RELEVANT,
            feedUrls: ['esteri' => 'https://www.corriere.it/esteri.xml'],
        );

        $results = $aggregator->fetch();

        self::assertCount(1, $results);
        self::assertContains('esteri', $results[0]->keywords);
    }

    public function testResultPreservesPublishedAt(): void
    {
        $aggregator = $this->createAggregator(
            rssXml: self::RSS_WITH_RELEVANT,
            feedUrls: ['esteri' => 'https://www.corriere.it/esteri.xml'],
        );

        $results = $aggregator->fetch();

        self::assertCount(1, $results);
        self::assertSame('2026-04-06', $results[0]->publishedAt->format('Y-m-d'));
    }

    private function createAggregator(string $rssXml, array $feedUrls): CorriereAggregator
    {
        $httpClient = new MockHttpClient(new MockResponse($rssXml));

        return new CorriereAggregator(
            rssFeedParser: new RssFeedParser($httpClient, new NullLogger(), 'TestBot/1.0', 30),
            relevanceFilter: $this->createRelevanceFilter(),
            logger: new NullLogger(),
            enabled: true,
            feedUrls: $feedUrls,
            rateLimitMs: 0,
        );
    }

    private function createDisabledAggregator(): CorriereAggregator
    {
        $httpClient = new MockHttpClient(new MockResponse(''));

        return new CorriereAggregator(
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
