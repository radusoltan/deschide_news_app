<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Aggregator;

use App\Dto\Aggregator\AggregatorResult;
use App\Enum\AggregatorSourceType;
use App\Service\Aggregator\AggregatorRateLimiter;
use App\Service\Aggregator\GoogleNewsRssAggregator;
use App\Service\Scraping\RssFeedParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(GoogleNewsRssAggregator::class)]
class GoogleNewsRssAggregatorTest extends TestCase
{
    private const RSS_FIXTURE = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Moldova - Google News</title>
    <item>
      <title>Moldovan citizen wins international prize - Moldova 1</title>
      <link>https://news.google.com/rss/articles/CBMiQEFV123</link>
      <description>A Moldovan citizen won an international prize today.</description>
      <pubDate>Sun, 06 Apr 2026 10:00:00 GMT</pubDate>
      <source url="https://moldova1.md">Moldova 1</source>
    </item>
    <item>
      <title>Moldova EU integration update - G4Media</title>
      <link>https://news.google.com/rss/articles/CBMiQEFV456</link>
      <description>Latest updates on Moldova EU integration process.</description>
      <pubDate>Sun, 06 Apr 2026 09:00:00 GMT</pubDate>
      <source url="https://www.g4media.ro">G4Media</source>
    </item>
  </channel>
</rss>
XML;

    public function testFetchReturnsAggregatorResults(): void
    {
        $aggregator = $this->createAggregator(self::RSS_FIXTURE);
        $results = $aggregator->fetch();

        self::assertNotEmpty($results);
        self::assertContainsOnlyInstancesOf(AggregatorResult::class, $results);
        self::assertSame('Moldovan citizen wins international prize - Moldova 1', $results[0]->title);
        self::assertSame('en', $results[0]->sourceLanguage);
        self::assertSame(AggregatorSourceType::GOOGLE_NEWS_RSS, $results[0]->aggregatorSourceType);
        // Source tag fields
        self::assertSame('Moldova 1', $results[0]->sourceName);
        self::assertSame('moldova1.md', $results[0]->sourcePublisherDomain);
    }

    public function testSourcePublisherDomainStripsWww(): void
    {
        $aggregator = $this->createAggregator(self::RSS_FIXTURE);
        $results = $aggregator->fetch();

        // Second item has source url="https://www.g4media.ro" → domain "g4media.ro"
        self::assertSame('g4media.ro', $results[1]->sourcePublisherDomain);
        self::assertSame('G4Media', $results[1]->sourceName);
    }

    public function testFetchReturnsEmptyWhenDisabled(): void
    {
        $aggregator = $this->createAggregator(self::RSS_FIXTURE, enabled: false);
        $results = $aggregator->fetch();

        self::assertSame([], $results);
    }

    public function testFetchReturnsEmptyWhenRateLimited(): void
    {
        $aggregator = $this->createAggregator(self::RSS_FIXTURE, rateLimitAllowed: false);
        $results = $aggregator->fetch();

        self::assertSame([], $results);
    }

    public function testGetSourceType(): void
    {
        $aggregator = $this->createAggregator('');
        self::assertSame(AggregatorSourceType::GOOGLE_NEWS_RSS, $aggregator->getSourceType());
    }

    public function testGetName(): void
    {
        $aggregator = $this->createAggregator('');
        self::assertSame('Google News RSS', $aggregator->getName());
    }

    public function testFetchAggregatesMultipleLocalesAndKeywords(): void
    {
        $mockClient = new MockHttpClient(array_fill(0, 3, new MockResponse(self::RSS_FIXTURE)));
        $parser = new RssFeedParser($mockClient, new NullLogger(), 'TestAgent/1.0', 30);

        $rateLimiter = $this->createMock(AggregatorRateLimiter::class);
        $rateLimiter->method('isAllowed')->willReturn(true);

        $aggregator = new GoogleNewsRssAggregator(
            rssFeedParser: $parser,
            rateLimiter: $rateLimiter,
            logger: new NullLogger(),
            keywords: [
                'ro' => ['moldovean', 'Republica Moldova'],
                'en' => ['Moldovan citizen'],
            ],
            locales: [
                'ro' => ['hl' => 'ro', 'gl' => 'RO', 'ceid' => 'RO:ro'],
                'en' => ['hl' => 'en-US', 'gl' => 'US', 'ceid' => 'US:en'],
            ],
            userAgents: ['Mozilla/5.0 Test Agent'],
        );

        $results = $aggregator->fetch();
        // 3 keywords total * 2 items per feed = 6 results
        self::assertCount(6, $results);
    }

    private function createAggregator(
        string $rssXml,
        bool $enabled = true,
        bool $rateLimitAllowed = true,
        ?array $keywords = null,
    ): GoogleNewsRssAggregator {
        $rateLimiter = $this->createMock(AggregatorRateLimiter::class);
        $rateLimiter->method('isAllowed')->willReturn($rateLimitAllowed);

        $mockClient = new MockHttpClient(new MockResponse($rssXml));
        $parser = new RssFeedParser($mockClient, new NullLogger(), 'TestAgent/1.0', 30);

        return new GoogleNewsRssAggregator(
            rssFeedParser: $parser,
            rateLimiter: $rateLimiter,
            logger: new NullLogger(),
            keywords: $keywords ?? ['en' => ['Moldovan citizen']],
            locales: [
                'ro' => ['hl' => 'ro', 'gl' => 'RO', 'ceid' => 'RO:ro'],
                'en' => ['hl' => 'en-US', 'gl' => 'US', 'ceid' => 'US:en'],
            ],
            userAgents: ['Mozilla/5.0 Test Agent'],
            enabled: $enabled,
        );
    }
}
