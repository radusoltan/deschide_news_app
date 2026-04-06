<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Aggregator;

use App\Dto\Aggregator\AggregatorResult;
use App\Enum\AggregatorSourceType;
use App\Service\Aggregator\FacebookRssBridgeAggregator;
use App\Service\Scraping\RssFeedParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(FacebookRssBridgeAggregator::class)]
class FacebookRssBridgeAggregatorTest extends TestCase
{
    private string $fixtureXml;

    private const PAGES = [
        ['name' => 'Moldoveni în Italia', 'page_id' => 'moldoveniitalia', 'language' => 'ro'],
    ];

    protected function setUp(): void
    {
        $this->fixtureXml = file_get_contents(__DIR__ . '/../../../Fixtures/rss-bridge-facebook-feed-sample.xml');
    }

    public function testFetchReturnsEmptyWhenDisabled(): void
    {
        $aggregator = $this->createAggregator(responseBody: $this->fixtureXml, enabled: false);

        self::assertSame([], $aggregator->fetch());
    }

    public function testFetchReturnsEmptyWhenNoBaseUrl(): void
    {
        $aggregator = $this->createAggregator(responseBody: $this->fixtureXml, baseUrl: '');

        self::assertSame([], $aggregator->fetch());
    }

    public function testFetchReturnsMappedResults(): void
    {
        $aggregator = $this->createAggregator(responseBody: $this->fixtureXml);
        $results = $aggregator->fetch();

        self::assertCount(2, $results);
        self::assertInstanceOf(AggregatorResult::class, $results[0]);
        self::assertSame('Un moldovean din Roma a câștigat premiul regional de integrare', $results[0]->title);
        self::assertSame('Moldoveni în Italia', $results[0]->sourceName);
        self::assertSame('ro', $results[0]->sourceLanguage);
        self::assertSame(AggregatorSourceType::FACEBOOK_RSS, $results[0]->aggregatorSourceType);
    }

    public function testFetchReturnsEmptyWhenFeedIsEmpty(): void
    {
        $emptyFeed = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Empty Feed</title>
</feed>
XML;

        $aggregator = $this->createAggregator(responseBody: $emptyFeed);
        $results = $aggregator->fetch();

        self::assertSame([], $results);
    }

    public function testFetchHandlesHttpError(): void
    {
        $mockClient = new MockHttpClient(new MockResponse('', ['http_code' => 500]));
        $parser = new RssFeedParser($mockClient, new NullLogger(), 'TestAgent/1.0', 30);

        $aggregator = new FacebookRssBridgeAggregator(
            rssFeedParser: $parser,
            logger: new NullLogger(),
            enabled: true,
            baseUrl: 'http://rss-bridge:80',
            pages: self::PAGES,
            rateLimitSeconds: 0,
        );

        // Should not throw — graceful error handling
        $results = $aggregator->fetch();
        self::assertSame([], $results);
    }

    public function testGetSourceType(): void
    {
        $aggregator = $this->createAggregator(responseBody: '');

        self::assertSame(AggregatorSourceType::FACEBOOK_RSS, $aggregator->getSourceType());
    }

    public function testGetName(): void
    {
        $aggregator = $this->createAggregator(responseBody: '');

        self::assertSame('Facebook RSS Bridge', $aggregator->getName());
    }

    public function testSecondResultHasCorrectData(): void
    {
        $aggregator = $this->createAggregator(responseBody: $this->fixtureXml);
        $results = $aggregator->fetch();

        self::assertCount(2, $results);
        self::assertSame('Eveniment: Ziua Moldovei la Milano - 15 aprilie', $results[1]->title);
        self::assertSame('2026-04-05', $results[1]->publishedAt->format('Y-m-d'));
    }

    private function createAggregator(
        string $responseBody,
        bool $enabled = true,
        string $baseUrl = 'http://rss-bridge:80',
    ): FacebookRssBridgeAggregator {
        $mockClient = new MockHttpClient(new MockResponse($responseBody));
        $parser = new RssFeedParser($mockClient, new NullLogger(), 'TestAgent/1.0', 30);

        return new FacebookRssBridgeAggregator(
            rssFeedParser: $parser,
            logger: new NullLogger(),
            enabled: $enabled,
            baseUrl: $baseUrl,
            pages: self::PAGES,
            rateLimitSeconds: 0,
        );
    }
}
