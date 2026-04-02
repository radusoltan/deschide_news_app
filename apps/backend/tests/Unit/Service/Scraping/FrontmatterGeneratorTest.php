<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Scraping;

use App\Dto\Scraping\ScrapedContent;
use App\Service\RomanianSlugger;
use App\Service\Scraping\FrontmatterGenerator;
use PHPUnit\Framework\TestCase;

class FrontmatterGeneratorTest extends TestCase
{
    private FrontmatterGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new FrontmatterGenerator(new RomanianSlugger());
    }

    public function testGeneratesCompleteFrontmatter(): void
    {
        $content = new ScrapedContent(
            url: 'https://moldpres.md/news/123',
            title: 'Guvernul aprobă noul plan de reformă',
            bodyHtml: '<p>Conținut</p>',
            bodyText: 'Conținut complet al articolului despre reforma guvernamentală.',
            language: 'ro',
            sourceName: 'Moldpres',
            publishedAt: new \DateTimeImmutable('2026-04-02T10:00:00+00:00'),
        );

        $fm = $this->generator->buildFrontmatter($content);

        $this->assertStringStartsWith('art-2026-04-02-', $fm['id']);
        $this->assertSame('press-release', $fm['type']);
        $this->assertSame('ro', $fm['language']);
        $this->assertSame('Guvernul aprobă noul plan de reformă', $fm['title']['ro']);
        $this->assertSame('draft', $fm['status']);
        $this->assertSame('Moldpres', $fm['source']['name']);
        $this->assertSame('https://moldpres.md/news/123', $fm['source']['url']);
        $this->assertSame('ro', $fm['source']['original_language']);
        $this->assertTrue($fm['ai']['auto_generated']);
        $this->assertFalse($fm['ai']['reviewed']);
        $this->assertSame('complete', $fm['ai']['translation_status']['ro']);
    }

    public function testGeneratesMarkdownDocument(): void
    {
        $content = new ScrapedContent(
            url: 'https://ipn.md/ro/test',
            title: 'Titlu test',
            bodyHtml: '<p>Body</p>',
            bodyText: 'Body text.',
            language: 'ro',
            sourceName: 'IPN',
        );

        $document = $this->generator->generate($content, 'Body markdown content.');

        $this->assertStringStartsWith('---', $document);
        $this->assertStringContainsString('id: art-', $document);
        $this->assertStringContainsString('type: press-release', $document);
        $this->assertStringContainsString('Body markdown content.', $document);
        // Verify frontmatter is properly delimited
        $this->assertSame(2, substr_count($document, '---'));
    }

    public function testDescriptionIsTruncatedTo300Chars(): void
    {
        $longText = str_repeat('Cuvânt ', 100); // ~700 chars
        $content = new ScrapedContent(
            url: 'https://test.md',
            title: 'Test',
            bodyHtml: '',
            bodyText: $longText,
            language: 'ro',
            sourceName: 'Test',
        );

        $fm = $this->generator->buildFrontmatter($content);

        $this->assertLessThanOrEqual(300, mb_strlen($fm['description']['ro']));
    }

    public function testIdIsTruncatedTo128Chars(): void
    {
        $longTitle = str_repeat('cuvânt-lung-', 20); // Very long title
        $content = new ScrapedContent(
            url: 'https://test.md',
            title: $longTitle,
            bodyHtml: '',
            bodyText: 'Text.',
            language: 'ro',
            sourceName: 'Test',
        );

        $fm = $this->generator->buildFrontmatter($content);

        $this->assertLessThanOrEqual(128, mb_strlen($fm['id']));
    }
}
