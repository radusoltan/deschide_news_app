<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Aggregator\Portal;

use App\Dto\Aggregator\AggregatorResult;
use App\Enum\AggregatorSourceType;
use App\Service\Aggregator\Portal\GuardianApiAggregator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(GuardianApiAggregator::class)]
class GuardianApiAggregatorTest extends TestCase
{
    private string $fixtureJson;

    protected function setUp(): void
    {
        $this->fixtureJson = file_get_contents(__DIR__ . '/../../../../Fixtures/guardian-api-response-sample.json');
    }

    public function testFetchReturnsAggregatorResults(): void
    {
        $aggregator = $this->createAggregator($this->fixtureJson);
        $results = $aggregator->fetch();

        self::assertCount(2, $results);
        self::assertInstanceOf(AggregatorResult::class, $results[0]);
        self::assertSame('Moldova makes significant progress on EU integration path', $results[0]->title);
        self::assertSame('The Guardian', $results[0]->sourceName);
        self::assertSame('en', $results[0]->sourceLanguage);
        self::assertSame(AggregatorSourceType::DIRECT_PORTAL, $results[0]->aggregatorSourceType);
    }

    public function testFetchParsesPublishedDate(): void
    {
        $aggregator = $this->createAggregator($this->fixtureJson);
        $results = $aggregator->fetch();

        self::assertSame('2026-04-06', $results[0]->publishedAt->format('Y-m-d'));
    }

    public function testFetchExtractsTrailText(): void
    {
        $aggregator = $this->createAggregator($this->fixtureJson);
        $results = $aggregator->fetch();

        self::assertStringContainsString('Moldovan government implements key reforms', $results[0]->summary);
    }

    public function testFetchExtractsWebUrl(): void
    {
        $aggregator = $this->createAggregator($this->fixtureJson);
        $results = $aggregator->fetch();

        self::assertSame(
            'https://www.theguardian.com/world/2026/apr/06/moldova-eu-integration-progress',
            $results[0]->sourceUrl,
        );
    }

    public function testFetchReturnsEmptyWhenDisabled(): void
    {
        $aggregator = $this->createAggregator($this->fixtureJson, enabled: false);

        self::assertSame([], $aggregator->fetch());
    }

    public function testFetchReturnsEmptyWhenNoApiKey(): void
    {
        $aggregator = $this->createAggregator($this->fixtureJson, apiKey: '');

        self::assertSame([], $aggregator->fetch());
    }

    public function testFetchHandlesHttpError(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('', ['http_code' => 500]));

        $aggregator = new GuardianApiAggregator(
            httpClient: $httpClient,
            logger: new NullLogger(),
            apiKey: 'test-key',
            enabled: true,
            keywords: ['Moldova'],
        );

        $results = $aggregator->fetch();
        self::assertSame([], $results);
    }

    public function testGetSourceType(): void
    {
        $aggregator = $this->createAggregator('{}', enabled: false);

        self::assertSame(AggregatorSourceType::DIRECT_PORTAL, $aggregator->getSourceType());
    }

    public function testGetName(): void
    {
        $aggregator = $this->createAggregator('{}', enabled: false);

        self::assertSame('The Guardian API', $aggregator->getName());
    }

    private function createAggregator(
        string $responseBody,
        bool $enabled = true,
        string $apiKey = 'test-key',
    ): GuardianApiAggregator {
        $httpClient = new MockHttpClient(new MockResponse($responseBody));

        return new GuardianApiAggregator(
            httpClient: $httpClient,
            logger: new NullLogger(),
            apiKey: $apiKey,
            enabled: $enabled,
            keywords: ['Moldovan', 'Moldova', 'Transnistria'],
        );
    }
}
