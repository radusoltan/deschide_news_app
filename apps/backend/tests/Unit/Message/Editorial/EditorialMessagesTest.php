<?php

declare(strict_types=1);

namespace App\Tests\Unit\Message\Editorial;

use App\Message\Editorial\ProcessScrapedArticleMessage;
use App\Message\Editorial\ScrapeSourceMessage;
use PHPUnit\Framework\TestCase;

class EditorialMessagesTest extends TestCase
{
    public function testScrapeSourceMessageProperties(): void
    {
        $msg = new ScrapeSourceMessage('moldpres', 'ro', 10);

        $this->assertSame('moldpres', $msg->sourceKey);
        $this->assertSame('ro', $msg->language);
        $this->assertSame(10, $msg->limit);
    }

    public function testScrapeSourceMessageDefaults(): void
    {
        $msg = new ScrapeSourceMessage('ipn');

        $this->assertSame('ipn', $msg->sourceKey);
        $this->assertNull($msg->language);
        $this->assertSame(30, $msg->limit);
    }

    public function testProcessScrapedArticleMessageProperties(): void
    {
        $publishedAt = new \DateTimeImmutable('2026-04-02');
        $msg = new ProcessScrapedArticleMessage(
            title: 'Titlu test',
            bodyMarkdown: '# Content',
            sourceUrl: 'https://moldpres.md/123',
            sourceName: 'Moldpres',
            originalLanguage: 'ro',
            contentHash: 'abc123def456',
            publishedAt: $publishedAt,
        );

        $this->assertSame('Titlu test', $msg->title);
        $this->assertSame('# Content', $msg->bodyMarkdown);
        $this->assertSame('https://moldpres.md/123', $msg->sourceUrl);
        $this->assertSame('Moldpres', $msg->sourceName);
        $this->assertSame('ro', $msg->originalLanguage);
        $this->assertSame('abc123def456', $msg->contentHash);
        $this->assertSame($publishedAt, $msg->publishedAt);
    }

    public function testProcessScrapedArticleMessageDefaultPublishedAt(): void
    {
        $msg = new ProcessScrapedArticleMessage(
            title: 'Test',
            bodyMarkdown: 'Body',
            sourceUrl: 'https://test.md',
            sourceName: 'Test',
            originalLanguage: 'ro',
            contentHash: 'hash123',
        );

        $this->assertNull($msg->publishedAt);
    }
}
