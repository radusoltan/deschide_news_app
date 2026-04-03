<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

class ScrapedContentCleaner
{
    public function __construct(
        private readonly HtmlSanitizerInterface $pressContentSanitizer,
    ) {}

    public function clean(string $rawHtml): string
    {
        // Remove script, style, iframe, form blocks
        $html = preg_replace('/<script[^>]*>.*?<\/script>/si', '', $rawHtml) ?? $rawHtml;
        $html = preg_replace('/<style[^>]*>.*?<\/style>/si', '', $html) ?? $html;
        $html = preg_replace('/<iframe[^>]*>.*?<\/iframe>/si', '', $html) ?? $html;
        $html = preg_replace('/<form[^>]*>.*?<\/form>/si', '', $html) ?? $html;

        // Remove structural/Drupal boilerplate elements
        $html = preg_replace('/<nav[^>]*>.*?<\/nav>/si', '', $html) ?? $html;
        $html = preg_replace('/<header[^>]*>.*?<\/header>/si', '', $html) ?? $html;
        $html = preg_replace('/<footer[^>]*>.*?<\/footer>/si', '', $html) ?? $html;
        $html = preg_replace('/<aside[^>]*>.*?<\/aside>/si', '', $html) ?? $html;

        // Remove elements with Drupal-specific classes
        $html = preg_replace('/<[^>]+class="[^"]*(?:field--name-|contextual-|block-system-|region-)[^"]*"[^>]*>.*?<\/[^>]+>/si', '', $html) ?? $html;

        // Apply Symfony HtmlSanitizer
        $html = $this->pressContentSanitizer->sanitize($html);

        // Trim excessive whitespace
        $html = preg_replace('/\s+/', ' ', $html) ?? $html;
        $html = preg_replace('/>\s+</', '><', $html) ?? $html;

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
}
