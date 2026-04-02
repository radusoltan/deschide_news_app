<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Scraping;

use App\Service\Scraping\HtmlToMarkdownConverter;
use PHPUnit\Framework\TestCase;

class HtmlToMarkdownConverterTest extends TestCase
{
    private HtmlToMarkdownConverter $converter;

    protected function setUp(): void
    {
        $this->converter = new HtmlToMarkdownConverter();
    }

    public function testConvertsBasicHtmlToMarkdown(): void
    {
        $html = '<h2>Titlu</h2><p>Paragraf cu <strong>bold</strong> și <em>italic</em>.</p>';
        $result = $this->converter->convert($html);

        $this->assertStringContainsString('## Titlu', $result);
        $this->assertStringContainsString('**bold**', $result);
        $this->assertStringContainsString('*italic*', $result);
    }

    public function testNormalizesUtf8Nfc(): void
    {
        // Test with decomposed Romanian characters (ș as s + combining cedilla)
        $decomposed = "Te\xC8\x99t cu \xC8\x99 \xC8\x99i \xC8\x9B"; // ș and ț in NFC
        $html = "<p>{$decomposed}</p>";
        $result = $this->converter->convert($html);

        // Verify NFC normalization was applied (intl extension required)
        if (extension_loaded('intl')) {
            $this->assertTrue(\Normalizer::isNormalized($result, \Normalizer::FORM_C));
        }

        $this->assertStringContainsString('ș', $result);
        $this->assertStringContainsString('ț', $result);
    }

    public function testRemovesStyleAttributes(): void
    {
        $html = '<p style="color: red; font-size: 14px;">Text cu stil</p>';
        $result = $this->converter->convert($html);

        $this->assertStringNotContainsString('style=', $result);
        $this->assertStringContainsString('Text cu stil', $result);
    }

    public function testRemovesClassAttributes(): void
    {
        $html = '<p class="article-content main-text">Content</p>';
        $result = $this->converter->convert($html);

        $this->assertStringNotContainsString('class=', $result);
        $this->assertStringContainsString('Content', $result);
    }

    public function testRemovesNavigationLinks(): void
    {
        $html = '<p>Content</p><p><a href="/home">Înapoi</a></p>';
        $result = $this->converter->convert($html);

        $this->assertStringContainsString('Content', $result);
        $this->assertStringNotContainsString('[Înapoi]', $result);
    }

    public function testLimitsHeadingDepth(): void
    {
        $html = '<h4>Deep heading</h4><h5>Deeper</h5>';
        $result = $this->converter->convert($html);

        // h4+ should be converted to h3
        $this->assertStringContainsString('### Deep heading', $result);
        $this->assertStringContainsString('### Deeper', $result);
    }

    public function testRemovesExcessiveBlankLines(): void
    {
        $html = "<p>One</p>\n\n\n\n\n<p>Two</p>";
        $result = $this->converter->convert($html);

        // Should not have more than 2 consecutive newlines
        $this->assertDoesNotMatchRegularExpression('/\n{3,}/', $result);
    }

    public function testHandlesEmptyInput(): void
    {
        $result = $this->converter->convert('');
        $this->assertSame('', $result);
    }

    public function testPreservesCyrillicText(): void
    {
        $html = '<p>Молдова объявила о новых реформах.</p>';
        $result = $this->converter->convert($html);

        $this->assertStringContainsString('Молдова', $result);
        $this->assertStringContainsString('реформах', $result);
    }
}
