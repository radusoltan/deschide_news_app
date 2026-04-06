<?php

declare(strict_types=1);

namespace App\Service\Aggregator;

use Symfony\Component\DomCrawler\Crawler;

class GoogleAlertEmailParser
{
    /**
     * Parse a Google Alerts HTML email and extract article links.
     *
     * @return list<array{title: string, url: string, snippet: string}>
     */
    public function parse(string $htmlContent): array
    {
        $crawler = new Crawler($htmlContent);
        $items = [];

        $crawler->filter('a[href]')->each(function (Crawler $node) use (&$items): void {
            $href = $node->attr('href') ?? '';
            $text = trim($node->text(''));

            // Skip non-article links (unsubscribe, settings, google.com internal)
            if ($this->shouldSkipUrl($href) || $text === '') {
                return;
            }

            // Google Alerts wraps URLs in redirects — extract the real URL
            $realUrl = $this->extractRealUrl($href);
            if ($realUrl === null) {
                return;
            }

            // Try to extract snippet from the parent container
            $snippet = '';
            $parent = $node->closest('td, div');
            if ($parent !== null && $parent->count() > 0) {
                $fullText = trim($parent->text(''));
                $snippet = str_replace($text, '', $fullText);
                $snippet = trim(preg_replace('/\s+/', ' ', $snippet));
            }

            $items[] = [
                'title' => $text,
                'url' => $realUrl,
                'snippet' => mb_substr($snippet, 0, 500),
            ];
        });

        return $items;
    }

    private function shouldSkipUrl(string $url): bool
    {
        $skipPatterns = [
            'google.com/alerts',
            'accounts.google.com',
            'support.google.com',
            'unsubscribe',
            'mailto:',
            '#',
        ];

        foreach ($skipPatterns as $pattern) {
            if (str_contains($url, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function extractRealUrl(string $url): ?string
    {
        // Google Alerts uses redirect URLs like:
        // https://www.google.com/url?rct=j&sa=t&url=https://example.com/article&...
        if (str_contains($url, 'google.com/url')) {
            $parsed = parse_url($url);
            if (isset($parsed['query'])) {
                parse_str($parsed['query'], $params);
                if (isset($params['url']) && filter_var($params['url'], \FILTER_VALIDATE_URL)) {
                    return $params['url'];
                }
            }

            return null;
        }

        // Direct URLs (some alerts contain direct links)
        if (filter_var($url, \FILTER_VALIDATE_URL) && str_starts_with($url, 'http')) {
            return $url;
        }

        return null;
    }
}
