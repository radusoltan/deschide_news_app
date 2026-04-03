<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class ScrapedContentCleaner
{
    public function __construct(
        private readonly HtmlSanitizerInterface $pressContentSanitizer,
    ) {}

    /**
     * Clean scraped content that may be a mix of Markdown and residual HTML.
     * Returns clean HTML suitable for editorial review.
     */
    public function clean(string $input): string
    {
        // Step 1: Strip ALL HTML tags — the input is Markdown-with-HTML-fragments
        $text = $this->stripAllHtml($input);

        // Step 2: Remove Markdown noise (image refs, link artifacts, heading markers for boilerplate)
        $text = $this->removeMarkdownNoise($text);

        // Step 3: Split into paragraphs and wrap in <p> tags
        $html = $this->textToHtml($text);

        // Step 4: Sanitize the clean HTML
        $html = $this->pressContentSanitizer->sanitize($html);

        return trim($html);
    }

    public function extractLead(string $cleanHtml, int $maxLength = 300): string
    {
        $text = strip_tags($cleanHtml);
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            return '';
        }

        // Split into sentences
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        if ($sentences === false || $sentences === []) {
            return mb_substr($text, 0, $maxLength);
        }

        // Build lead from complete sentences
        $lead = '';
        foreach ($sentences as $sentence) {
            $candidate = $lead === '' ? $sentence : $lead . ' ' . $sentence;
            if (mb_strlen($candidate) > $maxLength) {
                break;
            }
            $lead = $candidate;
        }

        // If even the first sentence exceeds maxLength, return it truncated
        if ($lead === '') {
            $lead = $sentences[0];
            if (mb_strlen($lead) > $maxLength) {
                $lead = mb_substr($lead, 0, $maxLength);
            }
        }

        return $lead;
    }

    private function stripAllHtml(string $input): string
    {
        // If input looks like a full HTML page, extract <body> or <article> content
        if (preg_match('/<html[\s>]/i', $input)) {
            $input = $this->extractBodyContent($input);
        }

        // Remove <head>, script, style, iframe, form blocks
        $input = preg_replace('/<head[^>]*>.*?<\/head>/si', '', $input) ?? $input;
        $input = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $input) ?? $input;
        $input = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $input) ?? $input;
        $input = preg_replace('/<iframe[^>]*>.*?<\/iframe>/si', '', $input) ?? $input;
        $input = preg_replace('/<form[^>]*>.*?<\/form>/si', '', $input) ?? $input;

        // Remove structural boilerplate elements
        $input = preg_replace('/<nav[^>]*>.*?<\/nav>/si', '', $input) ?? $input;
        $input = preg_replace('/<header[^>]*>.*?<\/header>/si', '', $input) ?? $input;
        $input = preg_replace('/<footer[^>]*>.*?<\/footer>/si', '', $input) ?? $input;
        $input = preg_replace('/<aside[^>]*>.*?<\/aside>/si', '', $input) ?? $input;

        // Convert <br> and block-level closing tags to newlines before stripping
        $input = preg_replace('/<br\s*\/?>/i', "\n", $input) ?? $input;
        $input = preg_replace('/<\/(p|div|h[1-6]|li|tr|article|section|blockquote|figure|figcaption)>/i', "\n\n", $input) ?? $input;

        // Strip all remaining HTML tags
        $text = strip_tags($input);

        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $text;
    }

    /**
     * Extract main content from a full HTML page.
     * Tries <article>, then main content div, then <body>.
     */
    private function extractBodyContent(string $html): string
    {
        // Try to find article content (Drupal pattern)
        if (preg_match('/<article[^>]*>(.*?)<\/article>/si', $html, $m)) {
            return $m[1];
        }

        // Try main content block
        if (preg_match('/<div[^>]+id="block-[^"]*mainpagecontent"[^>]*>(.*?)<\/div>\s*<div[^>]+id="block-/si', $html, $m)) {
            return $m[1];
        }

        // Fallback to <body>
        if (preg_match('/<body[^>]*>(.*?)<\/body>/si', $html, $m)) {
            return $m[1];
        }

        return $html;
    }

    private function removeMarkdownNoise(string $text): string
    {
        // Remove Markdown image references: ![alt](url)
        $text = preg_replace('/!\[[^\]]*\]\([^)]+\)/', '', $text) ?? $text;

        // Remove Markdown link artifacts: [text](url) → keep text
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text) ?? $text;

        // Remove standalone URLs on their own line
        $text = preg_replace('/^\s*https?:\/\/\S+\s*$/m', '', $text) ?? $text;

        // Remove noise lines (share buttons, navigation, boilerplate)
        $noisePatterns = [
            '/^\s*Distribuie:\s*$/mi',
            '/^\s*Print\s*$/mi',
            '/^\s*×\s*$/m',
            '/^\s*EUROPA PENTRU TINE!.*$/mi',
            '/^\s*Află despre beneficiile.*$/mi',
            '/^\s*Urmăriți-ne.*$/mi',
            '/^\s*©\s*\d{4}.*$/mi',
            '/^\s*All Rights Reserved.*$/mi',
            '/^\s*\d+\s*min\s*$/m',           // "1 min" reading time
            '/^\s*---+\s*$/m',                 // Horizontal rules
            '/^.*\|\s*Guvernul Republicii Moldova\s*$/mi',  // Gov.md page title
            '/^.*\|\s*gov\.md\s*$/mi',         // Gov.md page title variant
        ];
        foreach ($noisePatterns as $pattern) {
            $text = preg_replace($pattern, '', $text) ?? $text;
        }

        // Remove Markdown heading markers: # → nothing (we'll detect paragraphs by structure)
        // But preserve the heading text
        $text = preg_replace('/^#{1,6}\s+/m', '', $text) ?? $text;

        // Collapse 3+ newlines to 2
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return $text;
    }

    private function textToHtml(string $text): string
    {
        // Split into paragraphs by double newline
        $paragraphs = preg_split('/\n{2,}/', trim($text)) ?: [];

        $html = '';
        foreach ($paragraphs as $p) {
            // Collapse internal newlines to spaces (single-newline breaks within a paragraph)
            $p = preg_replace('/\n+/', ' ', $p) ?? $p;
            $p = preg_replace('/\s+/', ' ', $p) ?? $p;
            $p = trim($p);
            // Skip very short fragments (navigation artifacts, single words)
            if (mb_strlen($p) < 15) {
                continue;
            }
            // Skip lines that look like metadata (date stamps, counters)
            if (preg_match('/^\d{1,2}\s+\w{3,4}\s+\d{4}$/', $p)) {
                continue;
            }
            $html .= '<p>' . htmlspecialchars($p, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        return $html;
    }
}
