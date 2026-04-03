<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\ScrapedContentCleaner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class ScrapedContentCleanerTest extends TestCase
{
    private ScrapedContentCleaner $cleaner;

    protected function setUp(): void
    {
        // Create a mock sanitizer that strips disallowed tags
        $sanitizer = $this->createMock(HtmlSanitizerInterface::class);
        $sanitizer->method('sanitize')->willReturnCallback(function (string $html): string {
            // Simplified: strip all tags except allowed ones
            return strip_tags($html, '<p><br><strong><em><a><ul><ol><li><h2><h3><h4><blockquote><img>');
        });

        $this->cleaner = new ScrapedContentCleaner($sanitizer);
    }

    #[Test]
    public function removesScriptAndStyleTags(): void
    {
        $html = '<p>Content</p><script>alert("xss")</script><style>.x{}</style><p>More</p>';
        $clean = $this->cleaner->clean($html);

        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('style', $clean);
        $this->assertStringContainsString('Content', $clean);
        $this->assertStringContainsString('More', $clean);
    }

    #[Test]
    public function removesNavHeaderFooterAside(): void
    {
        $html = '<nav>Navigation</nav><header>Header</header><p>Main content</p><footer>Footer</footer><aside>Sidebar</aside>';
        $clean = $this->cleaner->clean($html);

        $this->assertStringNotContainsString('Navigation', $clean);
        $this->assertStringNotContainsString('Header', $clean);
        $this->assertStringNotContainsString('Footer', $clean);
        $this->assertStringNotContainsString('Sidebar', $clean);
        $this->assertStringContainsString('Main content', $clean);
    }

    #[Test]
    public function removesIframeAndForm(): void
    {
        $html = '<iframe src="x"></iframe><form action="/"><input /></form><p>Content</p>';
        $clean = $this->cleaner->clean($html);

        $this->assertStringNotContainsString('iframe', $clean);
        $this->assertStringNotContainsString('form', $clean);
        $this->assertStringContainsString('Content', $clean);
    }

    #[Test]
    public function extractLeadDoesNotCutMidSentence(): void
    {
        $html = '<p>First sentence here. Second sentence is longer and has more words. Third sentence.</p>';
        $lead = $this->cleaner->extractLead($html, 60);

        // Should include complete sentences only
        $this->assertStringEndsWith('.', $lead);
        $this->assertLessThanOrEqual(60, mb_strlen($lead));
    }

    #[Test]
    public function extractLeadFromEmptyReturnsEmpty(): void
    {
        $lead = $this->cleaner->extractLead('');
        $this->assertSame('', $lead);
    }

    #[Test]
    public function extractLeadReturnsFirstSentenceIfShort(): void
    {
        $html = '<p>Short lead text. Longer second sentence that extends past the limit.</p>';
        $lead = $this->cleaner->extractLead($html, 50);

        $this->assertSame('Short lead text.', $lead);
    }

    #[Test]
    public function extractLeadHandlesLongSingleSentence(): void
    {
        $html = '<p>This is one very long sentence without any sentence breaks that goes on and on and on and exceeds the maximum length for leads.</p>';
        $lead = $this->cleaner->extractLead($html, 50);

        // Should return the sentence truncated to maxLength
        $this->assertLessThanOrEqual(50, mb_strlen($lead));
    }
}
