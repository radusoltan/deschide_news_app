<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Aggregator;

use App\Dto\Aggregator\AggregatorResult;
use App\Dto\Aggregator\TrendQuery;
use App\Enum\AggregatorSourceType;
use App\Service\Aggregator\BingNewsAggregator;
use App\Service\Aggregator\TrendQueryGeneratorService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(BingNewsAggregator::class)]
class BingNewsAggregatorTest extends TestCase
{
    private string $fixtureJson;

    protected function setUp(): void
    {
        $this->fixtureJson = file_get_contents(__DIR__ . '/../../../Fixtures/bing-news-response-sample.json');
    }

    public function testFetchReturnsAggregatorResults(): void
    {
        $service = $this->createAggregator(
            responseBody: $this->fixtureJson,
            queries: [
                new TrendQuery(1, 'Transnistria', ['Transnistria'], 'en', AggregatorSourceType::BING_NEWS, 'Transnistria', 5.0),
            ],
        );

        $results = $service->fetch();

        self::assertCount(2, $results);
        self::assertInstanceOf(AggregatorResult::class, $results[0]);
        self::assertSame('Transnistria region faces energy crisis amid winter', $results[0]->title);
        self::assertSame('BBC News', $results[0]->sourceName);
        self::assertSame(AggregatorSourceType::BING_NEWS, $results[0]->aggregatorSourceType);
    }

    public function testFetchSkipsWhenNoApiKey(): void
    {
        $service = $this->createAggregator(
            responseBody: $this->fixtureJson,
            queries: [
                new TrendQuery(1, 'Test', ['test'], 'en', AggregatorSourceType::BING_NEWS, 'test', 1.0),
            ],
            apiKey: '',
        );

        self::assertSame([], $service->fetch());
    }

    public function testFetchFiltersOnlyBingNewsQueries(): void
    {
        $service = $this->createAggregator(
            responseBody: $this->fixtureJson,
            queries: [
                new TrendQuery(1, 'Test', ['test'], 'en', AggregatorSourceType::NEWS_API, 'test', 1.0),
            ],
        );

        self::assertSame([], $service->fetch());
    }

    public function testGetSourceType(): void
    {
        $service = $this->createAggregator('{}', []);

        self::assertSame(AggregatorSourceType::BING_NEWS, $service->getSourceType());
    }

    public function testGetName(): void
    {
        $service = $this->createAggregator('{}', []);

        self::assertSame('Bing News', $service->getName());
    }

    public function testParsesPublishedDate(): void
    {
        $service = $this->createAggregator(
            responseBody: $this->fixtureJson,
            queries: [
                new TrendQuery(1, 'Transnistria', ['Transnistria'], 'en', AggregatorSourceType::BING_NEWS, 'Transnistria', 5.0),
            ],
        );

        $results = $service->fetch();

        self::assertSame('2026-04-05', $results[0]->publishedAt->format('Y-m-d'));
    }

    /**
     * @param TrendQuery[] $queries
     */
    private function createAggregator(string $responseBody, array $queries, string $apiKey = 'test-key'): BingNewsAggregator
    {
        $httpClient = new MockHttpClient([
            new MockResponse($responseBody, ['http_code' => 200]),
        ]);

        $trendQueryGenerator = $this->createMock(TrendQueryGeneratorService::class);
        $trendQueryGenerator->method('getCachedQueries')->willReturn($queries);

        return new BingNewsAggregator(
            $httpClient,
            $trendQueryGenerator,
            new NullLogger(),
            $apiKey,
        );
    }
}
