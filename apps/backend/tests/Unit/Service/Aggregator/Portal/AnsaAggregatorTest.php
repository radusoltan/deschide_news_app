<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Aggregator\Portal;

use App\Dto\Aggregator\AggregatorResult;
use App\Enum\AggregatorSourceType;
use App\Service\Aggregator\Portal\AnsaAggregator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(AnsaAggregator::class)]
class AnsaAggregatorTest extends TestCase
{
    private string $fixtureHtml;

    protected function setUp(): void
    {
        $this->fixtureHtml = file_get_contents(__DIR__ . '/../../../../Fixtures/ansa-search-results-sample.html');
    }

    public function testFetchReturnsAggregatorResults(): void
    {
        $aggregator = $this->createAggregator($this->fixtureHtml, keywords: ['moldavo']);
        $results = $aggregator->fetch();

        self::assertCount(2, $results);
        self::assertInstanceOf(AggregatorResult::class, $results[0]);
        self::assertSame('Moldavo premiato a Roma per contributo culturale', $results[0]->title);
        self::assertSame('ANSA', $results[0]->sourceName);
        self::assertSame('it', $results[0]->sourceLanguage);
        self::assertSame(AggregatorSourceType::DIRECT_PORTAL, $results[0]->aggregatorSourceType);
    }

    public function testFetchMakesUrlsAbsolute(): void
    {
        $aggregator = $this->createAggregator($this->fixtureHtml, keywords: ['moldavo']);
        $results = $aggregator->fetch();

        self::assertStringStartsWith('https://www.ansa.it/', $results[0]->sourceUrl);
    }

    public function testFetchExtractsSnippet(): void
    {
        $aggregator = $this->createAggregator($this->fixtureHtml, keywords: ['moldavo']);
        $results = $aggregator->fetch();

        self::assertStringContainsString('cittadino moldavo', $results[0]->summary);
    }

    public function testFetchReturnsEmptyWhenDisabled(): void
    {
        $aggregator = $this->createAggregator($this->fixtureHtml, enabled: false);

        self::assertSame([], $aggregator->fetch());
    }

    public function testFetchHandlesNon200Response(): void
    {
        $httpClient = new MockHttpClient(new MockResponse('', ['http_code' => 403]));

        $aggregator = new AnsaAggregator(
            httpClient: $httpClient,
            logger: new NullLogger(),
            enabled: true,
            keywords: ['moldavo'],
            rateLimitMs: 0,
        );

        $results = $aggregator->fetch();
        self::assertSame([], $results);
    }

    public function testGetSourceType(): void
    {
        $aggregator = $this->createAggregator('', enabled: false);

        self::assertSame(AggregatorSourceType::DIRECT_PORTAL, $aggregator->getSourceType());
    }

    public function testGetName(): void
    {
        $aggregator = $this->createAggregator('', enabled: false);

        self::assertSame('ANSA.it', $aggregator->getName());
    }

    /**
     * @param list<string> $keywords
     */
    private function createAggregator(
        string $responseBody,
        bool $enabled = true,
        array $keywords = ['moldavo'],
    ): AnsaAggregator {
        $httpClient = new MockHttpClient(new MockResponse($responseBody));

        return new AnsaAggregator(
            httpClient: $httpClient,
            logger: new NullLogger(),
            enabled: $enabled,
            keywords: $keywords,
            rateLimitMs: 0,
        );
    }
}
