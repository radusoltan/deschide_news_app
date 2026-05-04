<?php

declare(strict_types=1);

namespace App\Tests\Service\Scraping;

use App\Service\Scraping\PythonScraperException;
use App\Service\Scraping\PythonScraperService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class PythonScraperServiceTest extends TestCase
{
    private PythonScraperService $service;

    protected function setUp(): void
    {
        // scraperPath is the Process cwd. Symfony\Component\Process throws
        // RuntimeException at construction if the cwd does not exist, which
        // would mask the actual error path the test is trying to verify
        // (PythonScraperException on URL fetch failure). Using a guaranteed
        // existing directory here lets the test exercise the real failure
        // mode regardless of dev/CI filesystem layout. T60.19-L4.
        $this->service = new PythonScraperService(
            logger: new NullLogger(),
            pythonBin: '/usr/bin/python3',
            scraperPath: sys_get_temp_dir(),
        );
    }

    public function testInvalidTypeThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid scraper type');

        $this->service->fetch('https://example.com', 'invalid-type');
    }

    public function testGetValidTypes(): void
    {
        $types = PythonScraperService::getValidTypes();

        self::assertContains('gov-rss', $types);
        self::assertContains('dom-scraper', $types);
        self::assertContains('pdf-extractor', $types);
        self::assertCount(3, $types);
    }

    /**
     * @group integration
     */
    public function testFetchGovRssReturnsValidStructure(): void
    {
        $result = $this->service->fetch(
            'https://gov.md/ro/rss.xml',
            'gov-rss',
            limit: 3,
        );

        self::assertArrayHasKey('source_type', $result);
        self::assertArrayHasKey('items', $result);
        self::assertArrayHasKey('errors', $result);
        self::assertArrayHasKey('scraped_at', $result);

        self::assertSame('gov-rss', $result['source_type']);
        self::assertIsArray($result['items']);
        self::assertIsArray($result['errors']);
        self::assertEmpty($result['errors']);
        self::assertLessThanOrEqual(3, \count($result['items']));
    }

    /**
     * @group integration
     */
    public function testFetchGovRssItemsHaveRequiredFields(): void
    {
        $result = $this->service->fetch(
            'https://gov.md/ro/rss.xml',
            'gov-rss',
            limit: 2,
        );

        self::assertNotEmpty($result['items'], 'Expected at least one item from gov.md RSS');

        foreach ($result['items'] as $item) {
            self::assertArrayHasKey('title', $item);
            self::assertArrayHasKey('content', $item);
            self::assertArrayHasKey('source_url', $item);
            self::assertArrayHasKey('source_name', $item);
            self::assertArrayHasKey('language', $item);
            self::assertArrayHasKey('attachments', $item);

            self::assertNotEmpty($item['title'], 'Title must not be empty');
            self::assertNotEmpty($item['content'], 'Content must not be empty');
            self::assertNotEmpty($item['source_url'], 'Source URL must not be empty');
            self::assertSame('gov.md', $item['source_name']);
            self::assertSame('ro', $item['language']);
            self::assertIsArray($item['attachments']);
        }
    }

    /**
     * @group integration
     */
    public function testFetchDomScraperReturnsValidStructure(): void
    {
        $result = $this->service->fetch(
            'http://www.meteo.md/',
            'dom-scraper',
            limit: 5,
        );

        self::assertArrayHasKey('source_type', $result);
        self::assertSame('dom-scraper', $result['source_type']);
        self::assertIsArray($result['items']);
        // meteo.md may or may not have active alerts — empty list is valid
    }

    public function testFetchInvalidUrlReturnsError(): void
    {
        $this->expectException(PythonScraperException::class);

        // Use a URL that will definitely fail
        $this->service->fetch(
            'http://this-domain-definitely-does-not-exist-xyz123.invalid/',
            'gov-rss',
            limit: 1,
        );
    }
}
