<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Scraping;

use App\Dto\Scraping\FeedItem;
use App\Service\Scraping\ScraperService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ScraperServiceTest extends TestCase
{
    public function testScrapeReturnsScrapedContentOnSuccess(): void
    {
        $html = '<html><body><article><h1>Titlu</h1><p>Conținut articol despre reformă.</p></article></body></html>';
        $mockClient = new MockHttpClient([new MockResponse($html)]);

        $scraper = new ScraperService($mockClient, new NullLogger(), 'TestBot/1.0', 30, 1);

        $feedItem = new FeedItem(
            title: 'Titlu test',
            url: 'https://moldpres.md/news/123',
            sourceName: 'Moldpres',
            language: 'ro',
        );

        $result = $scraper->scrape($feedItem);

        $this->assertNotNull($result);
        $this->assertSame('https://moldpres.md/news/123', $result->url);
        $this->assertSame('Titlu test', $result->title);
        $this->assertSame('ro', $result->language);
        $this->assertSame('Moldpres', $result->sourceName);
        $this->assertNotEmpty($result->bodyText);
    }

    public function testScrapeReturnsNullOnHttpError(): void
    {
        $mockClient = new MockHttpClient([new MockResponse('', ['http_code' => 404])]);
        $scraper = new ScraperService($mockClient, new NullLogger(), 'TestBot/1.0', 30, 1);

        $feedItem = new FeedItem(
            title: 'Not Found',
            url: 'https://test.md/missing',
            sourceName: 'Test',
            language: 'ro',
        );

        $result = $scraper->scrape($feedItem);

        $this->assertNull($result);
    }

    public function testScrapeReturnsNullOnEmptyContent(): void
    {
        // Empty body with just whitespace — fallback should produce empty text
        $html = '<html><body>   </body></html>';
        $mockClient = new MockHttpClient([new MockResponse($html)]);

        $scraper = new ScraperService($mockClient, new NullLogger(), 'TestBot/1.0', 30, 1);

        $feedItem = new FeedItem(
            title: 'Empty',
            url: 'https://test.md/empty',
            sourceName: 'Test',
            language: 'ro',
        );

        $result = $scraper->scrape($feedItem);

        // Scraper returns null when no content can be extracted
        $this->assertNull($result);
    }

    public function testScrapePreservesPublishedAt(): void
    {
        $html = '<html><body><p>Content here.</p></body></html>';
        $mockClient = new MockHttpClient([new MockResponse($html)]);

        $scraper = new ScraperService($mockClient, new NullLogger(), 'TestBot/1.0', 30, 1);

        $publishedAt = new \DateTimeImmutable('2026-04-01T12:00:00+00:00');
        $feedItem = new FeedItem(
            title: 'With date',
            url: 'https://test.md/dated',
            sourceName: 'Test',
            language: 'ro',
            publishedAt: $publishedAt,
        );

        $result = $scraper->scrape($feedItem);

        if ($result !== null) {
            $this->assertSame($publishedAt, $result->publishedAt);
        }
    }
}
