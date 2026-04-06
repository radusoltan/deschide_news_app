<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Aggregator;

use App\Dto\Aggregator\AggregatorResult;
use App\Dto\Aggregator\TrendQuery;
use App\Enum\AggregatorSourceType;
use App\Service\Aggregator\NewsApiAggregator;
use App\Service\Aggregator\TrendQueryGeneratorService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[CoversClass(NewsApiAggregator::class)]
class NewsApiAggregatorTest extends TestCase
{
    private string $fixtureJson;

    protected function setUp(): void
    {
        $this->fixtureJson = file_get_contents(__DIR__ . '/../../../Fixtures/newsapi-response-sample.json');
    }

    public function testFetchReturnsAggregatorResults(): void
    {
        $service = $this->createAggregator(
            responseBody: $this->fixtureJson,
            queries: [
                new TrendQuery(1, 'Moldova', ['Moldova'], 'en', AggregatorSourceType::NEWS_API, '"Moldova" OR "Moldovan"', 5.0),
            ],
        );

        $results = $service->fetch();

        self::assertCount(2, $results);
        self::assertInstanceOf(AggregatorResult::class, $results[0]);
        self::assertSame('Moldova signs EU integration agreement', $results[0]->title);
        self::assertSame('Reuters', $results[0]->sourceName);
        self::assertSame(AggregatorSourceType::NEWS_API, $results[0]->aggregatorSourceType);
        self::assertSame('en', $results[0]->sourceLanguage);
    }

    public function testFetchSkipsWhenNoApiKey(): void
    {
        $service = $this->createAggregator(
            responseBody: $this->fixtureJson,
            queries: [
                new TrendQuery(1, 'Test', ['test'], 'en', AggregatorSourceType::NEWS_API, 'test', 1.0),
            ],
            apiKey: '',
        );

        $results = $service->fetch();

        self::assertSame([], $results);
    }

    public function testFetchSkipsNonNewsApiQueries(): void
    {
        $service = $this->createAggregator(
            responseBody: $this->fixtureJson,
            queries: [
                // Only BING_NEWS queries — should be filtered out
                new TrendQuery(1, 'Test', ['test'], 'en', AggregatorSourceType::BING_NEWS, 'test', 1.0),
            ],
        );

        $results = $service->fetch();

        self::assertSame([], $results);
    }

    public function testFetchHandlesErrorResponse(): void
    {
        $errorJson = json_encode(['status' => 'error', 'message' => 'API key invalid']);
        $service = $this->createAggregator(
            responseBody: $errorJson,
            queries: [
                new TrendQuery(1, 'Test', ['test'], 'en', AggregatorSourceType::NEWS_API, 'test', 1.0),
            ],
        );

        $results = $service->fetch();

        self::assertSame([], $results);
    }

    public function testGetSourceTypeReturnsNewsApi(): void
    {
        $service = $this->createAggregator('{}', []);

        self::assertSame(AggregatorSourceType::NEWS_API, $service->getSourceType());
    }

    public function testGetNameReturnsNewsAPI(): void
    {
        $service = $this->createAggregator('{}', []);

        self::assertSame('NewsAPI', $service->getName());
    }

    public function testFetchParsesPublishedAt(): void
    {
        $service = $this->createAggregator(
            responseBody: $this->fixtureJson,
            queries: [
                new TrendQuery(1, 'Moldova', ['Moldova'], 'en', AggregatorSourceType::NEWS_API, '"Moldova"', 5.0),
            ],
        );

        $results = $service->fetch();

        self::assertSame('2026-04-06', $results[0]->publishedAt->format('Y-m-d'));
    }

    /**
     * @param TrendQuery[] $queries
     */
    private function createAggregator(string $responseBody, array $queries, string $apiKey = 'test-key'): NewsApiAggregator
    {
        $httpClient = new MockHttpClient([
            new MockResponse($responseBody, ['http_code' => 200]),
        ]);

        $trendQueryGenerator = $this->createMock(TrendQueryGeneratorService::class);
        $trendQueryGenerator->method('getCachedQueries')->willReturn($queries);

        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturnCallback(function (string $key, callable $callback) {
            $item = $this->createMock(ItemInterface::class);
            $item->method('expiresAfter');

            return $callback($item);
        });
        $cache->method('delete')->willReturn(true);

        return new NewsApiAggregator(
            $httpClient,
            $trendQueryGenerator,
            $cache,
            new NullLogger(),
            $apiKey,
        );
    }
}
