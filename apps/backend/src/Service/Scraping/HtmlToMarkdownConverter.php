<?php

declare(strict_types=1);

namespace App\Service\Scraping;

use League\HTMLToMarkdown\HtmlConverter;

final class HtmlToMarkdownConverter
{
    private readonly HtmlConverter $converter;

    public function __construct()
    {
        $this->converter = new HtmlConverter([
            'strip_tags' => false,
            'header_style' => 'atx',
            'remove_nodes' => 'script style nav footer aside header',
        ]);
    }

    /**
     * Convert HTML to clean Markdown with UTF-8 NFC normalization.
     *
     * @param array{strip_images?: bool, strip_links?: bool} $options
     */
    public function convert(string $html, array $options = []): string
    {
        // Pre-clean: remove unwanted elements and attributes
        $html = $this->cleanHtml($html);

        // Strip images before conversion if requested (removes <img>, <figure>, <figcaption>)
        if ($options['strip_images'] ?? false) {
            $html = preg_replace('/<figure[^>]*>.*?<\/figure>/si', '', $html);
            $html = preg_replace('/<img[^>]*\/?>/i', '', $html);
        }

        // Strip links if requested (keep text, remove <a> tags)
        if ($options['strip_links'] ?? false) {
            $html = preg_replace('/<a[^>]*>(.*?)<\/a>/si', '$1', $html);
        }

        // Convert to Markdown
        $markdown = $this->converter->convert($html);

        // Normalize UTF-8 to NFC (canonical form for ș/ț and Cyrillic)
        if (\extension_loaded('intl')) {
            $normalized = \Normalizer::normalize($markdown, \Normalizer::FORM_C);
            if ($normalized !== false) {
                $markdown = $normalized;
            }
        }

        // Post-process Markdown
        $markdown = $this->cleanMarkdown($markdown);

        return $markdown;
    }

    private function cleanHtml(string $html): string
    {
        // Remove inline style attributes
        $html = preg_replace('/\s+style="[^"]*"/i', '', $html);

        // Remove class attributes
        $html = preg_replace('/\s+class="[^"]*"/i', '', $html);

        // Remove data attributes
        $html = preg_replace('/\s+data-[a-z\-]+="[^"]*"/i', '', $html);

        // Remove onclick and other event handlers
        $html = preg_replace('/\s+on[a-z]+="[^"]*"/i', '', $html);

        return $html;
    }

    private function cleanMarkdown(string $markdown): string
    {
        // Remove excessive blank lines (3+ → 2)
        $markdown = preg_replace('/\n{3,}/', "\n\n", $markdown);

        // Remove navigation-style links (lines that are just links with no context)
        $markdown = preg_replace('/^\[(?:Înapoi|Back|Назад|Home|Acasă|Menu|Meniu)\]\([^)]+\)\s*$/mi', '', $markdown);

        // Normalize heading levels (no more than h3 for scraped content)
        $markdown = preg_replace('/^#{4,}\s/m', '### ', $markdown);

        // Trim leading/trailing whitespace
        $markdown = trim($markdown);

        return $markdown;
    }
}
