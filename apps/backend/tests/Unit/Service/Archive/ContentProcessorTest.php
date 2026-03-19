<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Archive;

use App\Service\Archive\ContentProcessor;
use Doctrine\DBAL\Connection;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit tests for ContentProcessor service.
 *
 * Tests shortcode transformation, HTML sanitization, and content processing
 * for legacy Newscoop/Beta article content.
 */
class ContentProcessorTest extends TestCase
{
    private ContentProcessor $processor;
    private Connection&MockObject $newscoopConnection;
    private Connection&MockObject $betaDeschideConnection;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->newscoopConnection = $this->createMock(Connection::class);
        $this->betaDeschideConnection = $this->createMock(Connection::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->processor = new ContentProcessor(
            $this->newscoopConnection,
            $this->betaDeschideConnection,
            $this->logger,
            '/images/alpha',
            '/images/beta'
        );
    }

    // ======================
    // Image Shortcode Tests
    // ======================

    #[Test]
    public function itTransformsImageShortcodeWithAllAttributes(): void
    {
        // Setup mock to return image data
        $this->newscoopConnection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'Id' => 160,
                'filename' => 'cms-image-000000160.jpg',
                'description' => 'Test image description',
                'width' => 800,
                'height' => 600,
            ]);

        $content = '<!** Image 1 align="left" alt="Alt text" sub="Caption text" width="400" height="300">';
        $result = $this->processor->processImageShortcodes($content, 122, ContentProcessor::SOURCE_NEWSCOOP, 2);

        $this->assertStringContainsString('<figure', $result);
        $this->assertStringContainsString('<img', $result);
        $this->assertStringContainsString('float-left', $result);
        $this->assertStringContainsString('alt="Alt text"', $result);
        $this->assertStringContainsString('<figcaption>Caption text</figcaption>', $result);
        $this->assertStringContainsString('width="400"', $result);
        $this->assertStringContainsString('height="300"', $result);
        $this->assertStringContainsString('/images/alpha/cms-image-000000160.jpg', $result);
    }

    #[Test]
    public function itTransformsImageShortcodeWithMinimalAttributes(): void
    {
        $this->newscoopConnection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'Id' => 160,
                'filename' => 'test-image.jpg',
                'description' => 'Default description',
                'width' => 1024,
                'height' => 768,
            ]);

        $content = '<!** Image 1>';
        $result = $this->processor->processImageShortcodes($content, 100, ContentProcessor::SOURCE_NEWSCOOP, 2);

        $this->assertStringContainsString('<figure class="figure">', $result);
        $this->assertStringContainsString('<img', $result);
        $this->assertStringContainsString('loading="lazy"', $result);
        $this->assertStringContainsString('alt="Default description"', $result);
    }

    #[Test]
    public function itHandlesImageNotFound(): void
    {
        $this->newscoopConnection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(false);

        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with(
                'Image not found for shortcode',
                $this->callback(function ($context) {
                    return $context['article_number'] === 100
                        && $context['attachment_number'] === 5
                        && $context['source'] === ContentProcessor::SOURCE_NEWSCOOP;
                })
            );

        $content = '<!** Image 5>';
        $result = $this->processor->processImageShortcodes($content, 100, ContentProcessor::SOURCE_NEWSCOOP, 2);

        $this->assertStringContainsString('<!-- Image 5 not found -->', $result);
    }

    #[Test]
    public function itUsesCorrectConnectionForBetaSource(): void
    {
        $this->betaDeschideConnection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn([
                'Id' => 100,
                'filename' => 'beta-image.jpg',
                'description' => 'Beta image',
                'width' => 640,
                'height' => 480,
            ]);

        // Newscoop connection should NOT be called
        $this->newscoopConnection
            ->expects($this->never())
            ->method('fetchAssociative');

        $content = '<!** Image 1>';
        $result = $this->processor->processImageShortcodes($content, 50, ContentProcessor::SOURCE_BETA, 2);

        $this->assertStringContainsString('/images/beta/beta-image.jpg', $result);
    }

    #[Test]
    #[DataProvider('imageAlignmentProvider')]
    public function itMapsImageAlignmentCorrectly(string $alignment, string $expectedClass): void
    {
        $this->newscoopConnection
            ->method('fetchAssociative')
            ->willReturn([
                'Id' => 1,
                'filename' => 'test.jpg',
                'description' => '',
                'width' => 100,
                'height' => 100,
            ]);

        $content = sprintf('<!** Image 1 align="%s">', $alignment);
        $result = $this->processor->processImageShortcodes($content, 1, ContentProcessor::SOURCE_NEWSCOOP, 2);

        $this->assertStringContainsString($expectedClass, $result);
    }

    public static function imageAlignmentProvider(): Generator
    {
        yield 'left alignment' => ['left', 'float-left'];
        yield 'right alignment' => ['right', 'float-right'];
        yield 'center alignment' => ['center', 'mx-auto'];
        yield 'middle alignment' => ['middle', 'mx-auto'];
    }

    #[Test]
    public function itProcessesMultipleImageShortcodes(): void
    {
        $this->newscoopConnection
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls(
                ['Id' => 1, 'filename' => 'image1.jpg', 'description' => '', 'width' => 100, 'height' => 100],
                ['Id' => 2, 'filename' => 'image2.jpg', 'description' => '', 'width' => 200, 'height' => 200],
                ['Id' => 3, 'filename' => 'image3.jpg', 'description' => '', 'width' => 300, 'height' => 300]
            );

        $content = 'Paragraph 1 <!** Image 1> Text <!** Image 2> More text <!** Image 3>';
        $result = $this->processor->processImageShortcodes($content, 1, ContentProcessor::SOURCE_NEWSCOOP, 2);

        $this->assertStringContainsString('image1.jpg', $result);
        $this->assertStringContainsString('image2.jpg', $result);
        $this->assertStringContainsString('image3.jpg', $result);
        $this->assertEquals(3, substr_count($result, '<figure'));
    }

    // ======================
    // Internal Link Tests
    // ======================

    #[Test]
    public function itTransformsInternalLinkShortcode(): void
    {
        $content = '<!** Link Internal IdPublication=1&amp;IdLanguage=2&amp;NrArticle=123>Link text<!** EndLink>';
        $result = $this->processor->processInternalLinks($content, ContentProcessor::SOURCE_NEWSCOOP);

        $this->assertStringContainsString('<a href="/arhiva/article/123">', $result);
        $this->assertStringContainsString('Link text</a>', $result);
        $this->assertStringNotContainsString('<!** Link', $result);
        $this->assertStringNotContainsString('EndLink', $result);
    }

    #[Test]
    public function itTransformsInternalLinkWithTarget(): void
    {
        $content = '<!** Link Internal IdPublication=1&IdLanguage=2&NrArticle=456 TARGET _blank>Click here<!** EndLink>';
        $result = $this->processor->processInternalLinks($content, ContentProcessor::SOURCE_NEWSCOOP);

        $this->assertStringContainsString('<a href="/arhiva/article/456" target="_blank">Click here</a>', $result);
    }

    #[Test]
    public function itHandlesBrokenInternalLink(): void
    {
        $this->logger
            ->expects($this->once())
            ->method('warning')
            ->with('Invalid internal link shortcode', $this->isType('array'));

        $content = '<!** Link Internal InvalidParams>Some text<!** EndLink>';
        $result = $this->processor->processInternalLinks($content, ContentProcessor::SOURCE_NEWSCOOP);

        // Should return just the link text without the anchor tag
        $this->assertEquals('Some text', $result);
    }

    #[Test]
    public function itProcessesMultipleInternalLinks(): void
    {
        $content = 'First <!** Link Internal NrArticle=100>link 1<!** EndLink> and second <!** Link Internal NrArticle=200>link 2<!** EndLink>.';
        $result = $this->processor->processInternalLinks($content, ContentProcessor::SOURCE_NEWSCOOP);

        $this->assertStringContainsString('/arhiva/article/100', $result);
        $this->assertStringContainsString('/arhiva/article/200', $result);
        $this->assertEquals(2, substr_count($result, '</a>'));
    }

    // ======================
    // Title Shortcode Tests
    // ======================

    #[Test]
    public function itTransformsTitleShortcode(): void
    {
        $content = '<!** Title>This is a subheading<!** EndTitle>';
        $result = $this->processor->processTitleShortcodes($content);

        $this->assertEquals('<h3 class="article-subheading">This is a subheading</h3>', $result);
    }

    #[Test]
    public function itProcessesMultipleTitleShortcodes(): void
    {
        $content = '<!** Title>First heading<!** EndTitle> Some text <!** Title>Second heading<!** EndTitle>';
        $result = $this->processor->processTitleShortcodes($content);

        $this->assertStringContainsString('<h3 class="article-subheading">First heading</h3>', $result);
        $this->assertStringContainsString('<h3 class="article-subheading">Second heading</h3>', $result);
        $this->assertEquals(2, substr_count($result, '</h3>'));
    }

    #[Test]
    public function itPreservesHtmlInsideTitleShortcode(): void
    {
        $content = '<!** Title><strong>Bold</strong> title<!** EndTitle>';
        $result = $this->processor->processTitleShortcodes($content);

        $this->assertStringContainsString('<strong>Bold</strong>', $result);
    }

    // ======================
    // Snippet Removal Tests
    // ======================

    #[Test]
    public function itRemovesSnippetShortcodes(): void
    {
        $content = 'Text before <!-- Snippet 123 --> text after';
        $result = $this->processor->removeSnippets($content);

        $this->assertEquals('Text before  text after', $result);
        $this->assertStringNotContainsString('Snippet', $result);
    }

    #[Test]
    public function itRemovesMultipleSnippets(): void
    {
        $content = '<!-- Snippet 1 --> Text <!-- Snippet 2 --> More <!-- Snippet 99 -->';
        $result = $this->processor->removeSnippets($content);

        $this->assertEquals(' Text  More ', $result);
    }

    // ======================
    // HTML Sanitization Tests
    // ======================

    #[Test]
    public function itRemovesEmptyTags(): void
    {
        $content = '<p></p><div>Content</div><span></span>';
        $result = $this->processor->sanitizeHtml($content);

        $this->assertStringNotContainsString('<p></p>', $result);
        $this->assertStringNotContainsString('<span></span>', $result);
        $this->assertStringContainsString('<div>Content</div>', $result);
    }

    #[Test]
    public function itNormalizesMultipleNewlines(): void
    {
        $content = "Paragraph 1\n\n\n\n\nParagraph 2";
        $result = $this->processor->sanitizeHtml($content);

        $this->assertEquals("Paragraph 1\n\nParagraph 2", $result);
    }

    #[Test]
    public function itRemovesInlineStyles(): void
    {
        $content = '<div style="color: red; font-size: 20px;">Content</div>';
        $result = $this->processor->sanitizeHtml($content);

        $this->assertStringNotContainsString('style=', $result);
        $this->assertStringContainsString('<div>Content</div>', $result);
    }

    #[Test]
    public function itRemovesEventHandlers(): void
    {
        $content = '<div onclick="alert(\'xss\')" onmouseover="hack()">Content</div>';
        $result = $this->processor->sanitizeHtml($content);

        $this->assertStringNotContainsString('onclick=', $result);
        $this->assertStringNotContainsString('onmouseover=', $result);
        $this->assertStringContainsString('<div>Content</div>', $result);
    }

    #[Test]
    public function itTrimsWhitespace(): void
    {
        $content = '   Content with spaces   ';
        $result = $this->processor->sanitizeHtml($content);

        $this->assertEquals('Content with spaces', $result);
    }

    // ======================
    // Full Processing Tests
    // ======================

    #[Test]
    public function itProcessesContentWithAllShortcodes(): void
    {
        $this->newscoopConnection
            ->method('fetchAssociative')
            ->willReturn([
                'Id' => 1,
                'filename' => 'test.jpg',
                'description' => 'Test',
                'width' => 100,
                'height' => 100,
            ]);

        $content = '<!** Title>Heading<!** EndTitle> <!** Image 1> <!** Link Internal NrArticle=123>link<!** EndLink> <!-- Snippet 5 -->';

        $result = $this->processor->processContent($content, 1, ContentProcessor::SOURCE_NEWSCOOP, 2);

        // Verify all transformations applied
        $this->assertStringContainsString('<h3 class="article-subheading">Heading</h3>', $result);
        $this->assertStringContainsString('<figure', $result);
        $this->assertStringContainsString('<a href="/arhiva/article/123">link</a>', $result);
        $this->assertStringNotContainsString('Snippet', $result);
    }

    #[Test]
    public function itResetsStatsForEachProcessCall(): void
    {
        $this->newscoopConnection
            ->method('fetchAssociative')
            ->willReturn(['Id' => 1, 'filename' => 't.jpg', 'description' => '', 'width' => 1, 'height' => 1]);

        // First call
        $this->processor->processContent('<!** Image 1>', 1, ContentProcessor::SOURCE_NEWSCOOP, 2);
        $stats1 = $this->processor->getStats();
        $this->assertEquals(1, $stats1['images_processed']);

        // Second call should reset stats
        $this->processor->processContent('No shortcodes', 2, ContentProcessor::SOURCE_NEWSCOOP, 2);
        $stats2 = $this->processor->getStats();
        $this->assertEquals(0, $stats2['images_processed']);
    }

    // ======================
    // Statistics Tests
    // ======================

    #[Test]
    public function itTracksStatistics(): void
    {
        $this->newscoopConnection
            ->method('fetchAssociative')
            ->willReturnOnConsecutiveCalls(
                ['Id' => 1, 'filename' => 'i1.jpg', 'description' => '', 'width' => 1, 'height' => 1],
                false // Second image not found
            );

        $content = '<!** Image 1> <!** Image 2> <!** Title>T<!** EndTitle> <!** Link Internal NrArticle=1>L<!** EndLink> <!-- Snippet 1 -->';

        $this->processor->processContent($content, 1, ContentProcessor::SOURCE_NEWSCOOP, 2);

        $stats = $this->processor->getStats();
        $this->assertEquals(1, $stats['images_processed']);
        $this->assertEquals(1, $stats['images_not_found']);
        $this->assertEquals(1, $stats['titles_converted']);
        $this->assertEquals(1, $stats['links_processed']);
        $this->assertEquals(1, $stats['snippets_removed']);
    }

    #[Test]
    public function itResetsStatsManually(): void
    {
        $this->newscoopConnection
            ->method('fetchAssociative')
            ->willReturn(['Id' => 1, 'filename' => 't.jpg', 'description' => '', 'width' => 1, 'height' => 1]);

        $this->processor->processContent('<!** Image 1>', 1, ContentProcessor::SOURCE_NEWSCOOP, 2);
        $this->assertEquals(1, $this->processor->getStats()['images_processed']);

        $this->processor->resetStats();

        $stats = $this->processor->getStats();
        $this->assertEquals(0, $stats['images_processed']);
        $this->assertEquals(0, $stats['images_not_found']);
        $this->assertEquals(0, $stats['links_processed']);
        $this->assertEquals(0, $stats['links_broken']);
        $this->assertEquals(0, $stats['titles_converted']);
        $this->assertEquals(0, $stats['snippets_removed']);
    }

    // ======================
    // Helper Method Tests
    // ======================

    #[Test]
    public function itDetectsShortcodes(): void
    {
        $this->assertTrue($this->processor->hasShortcodes('<!** Image 1>'));
        $this->assertTrue($this->processor->hasShortcodes('<!** Link Internal NrArticle=1>text<!** EndLink>'));
        $this->assertTrue($this->processor->hasShortcodes('<!** Title>heading<!** EndTitle>'));
        $this->assertFalse($this->processor->hasShortcodes('No shortcodes here'));
        $this->assertFalse($this->processor->hasShortcodes('<p>Regular HTML</p>'));
    }

    #[Test]
    public function itCountsShortcodes(): void
    {
        $content = '<!** Image 1> <!** Image 2> <!** Title>T<!** EndTitle> <!** Link Internal NrArticle=1>L<!** EndLink> <!-- Snippet 5 --> <!-- Snippet 6 -->';

        $counts = $this->processor->countShortcodes($content);

        $this->assertEquals(2, $counts['images']);
        $this->assertEquals(1, $counts['internal_links']);
        $this->assertEquals(1, $counts['titles']);
        $this->assertEquals(2, $counts['snippets']);
    }

    #[Test]
    public function itReturnsZeroCountsForCleanContent(): void
    {
        $counts = $this->processor->countShortcodes('<p>Clean HTML content without shortcodes</p>');

        $this->assertEquals(0, $counts['images']);
        $this->assertEquals(0, $counts['internal_links']);
        $this->assertEquals(0, $counts['titles']);
        $this->assertEquals(0, $counts['snippets']);
    }

    // ======================
    // Cache Tests
    // ======================

    #[Test]
    public function itCachesImageLookups(): void
    {
        $this->newscoopConnection
            ->expects($this->once()) // Should only query once
            ->method('fetchAssociative')
            ->willReturn(['Id' => 1, 'filename' => 't.jpg', 'description' => '', 'width' => 1, 'height' => 1]);

        // Process same image shortcode twice in same content
        $content = '<!** Image 1> text <!** Image 1>';
        $this->processor->processImageShortcodes($content, 1, ContentProcessor::SOURCE_NEWSCOOP, 2);

        // Second image should use cached value
    }

    #[Test]
    public function itClearsImageCache(): void
    {
        $this->newscoopConnection
            ->expects($this->exactly(2)) // Should query twice after cache clear
            ->method('fetchAssociative')
            ->willReturn(['Id' => 1, 'filename' => 't.jpg', 'description' => '', 'width' => 1, 'height' => 1]);

        $this->processor->processImageShortcodes('<!** Image 1>', 1, ContentProcessor::SOURCE_NEWSCOOP, 2);
        $this->processor->clearImageCache();
        $this->processor->processImageShortcodes('<!** Image 1>', 1, ContentProcessor::SOURCE_NEWSCOOP, 2);
    }

    // ======================
    // Edge Case Tests
    // ======================

    #[Test]
    public function itHandlesEmptyContent(): void
    {
        $result = $this->processor->processContent('', 1, ContentProcessor::SOURCE_NEWSCOOP, 2);
        $this->assertEquals('', $result);
    }

    #[Test]
    public function itHandlesContentWithOnlyWhitespace(): void
    {
        $result = $this->processor->processContent('   ', 1, ContentProcessor::SOURCE_NEWSCOOP, 2);
        $this->assertEquals('', $result);
    }

    #[Test]
    public function itPreservesRegularHtml(): void
    {
        $content = '<p>Paragraph with <strong>bold</strong> and <em>italic</em> text.</p>';
        $result = $this->processor->processContent($content, 1, ContentProcessor::SOURCE_NEWSCOOP, 2);

        $this->assertStringContainsString('<p>Paragraph', $result);
        $this->assertStringContainsString('<strong>bold</strong>', $result);
        $this->assertStringContainsString('<em>italic</em>', $result);
    }

    #[Test]
    public function itHandlesDatabaseException(): void
    {
        $this->newscoopConnection
            ->method('fetchAssociative')
            ->willThrowException(new \Exception('Database connection failed'));

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Error looking up image', $this->isType('array'));

        $content = '<!** Image 1>';
        $result = $this->processor->processImageShortcodes($content, 1, ContentProcessor::SOURCE_NEWSCOOP, 2);

        $this->assertStringContainsString('<!-- Image 1 not found -->', $result);
    }

    #[Test]
    public function itEscapesSpecialCharactersInOutput(): void
    {
        $this->newscoopConnection
            ->method('fetchAssociative')
            ->willReturn([
                'Id' => 1,
                'filename' => 'test.jpg',
                'description' => 'Image with "quotes" & special <chars>',
                'width' => 100,
                'height' => 100,
            ]);

        $content = '<!** Image 1>';
        $result = $this->processor->processImageShortcodes($content, 1, ContentProcessor::SOURCE_NEWSCOOP, 2);

        // Check that special characters are properly escaped
        $this->assertStringContainsString('alt="Image with &quot;quotes&quot; &amp; special &lt;chars&gt;"', $result);
    }
}
