<?php

declare(strict_types=1);

namespace App\Service\Import;

/**
 * Sanitizes HTML content from the legacy deschide.md CSV export.
 *
 * Removes: <script>, ad iframes, inline styles, empty attributes, Webflow classes.
 * Keeps: editorial content (YouTube, Facebook, TikTok, Telegram iframes), semantic HTML tags.
 */
class LegacyHtmlSanitizer
{
    /** Allowed HTML tags (editorial content) */
    private const ALLOWED_TAGS = [
        'p', 'a', 'strong', 'em', 'u', 'b', 'i',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'ul', 'ol', 'li',
        'blockquote', 'figure', 'figcaption',
        'img', 'iframe', 'br', 'pre', 'code', 'sub', 'sup',
    ];

    /** Allowed attributes per tag */
    private const ALLOWED_ATTRS = [
        'a' => ['href', 'target', 'rel', 'title'],
        'img' => ['src', 'alt', 'width', 'height', 'loading'],
        'iframe' => ['src', 'width', 'height', 'frameborder', 'allowfullscreen', 'allow', 'title'],
    ];

    /** Ad iframe domains to remove */
    private const AD_IFRAME_DOMAINS = [
        'safeframe.googlesyndication.com',
        'googlesyndication.com',
        'doubleclick.net',
        'googleads.g.doubleclick.net',
        'player.bidmatic.io',
        'imasdk.googleapis.com',
    ];

    /** Editorial iframe domains to keep */
    private const EDITORIAL_IFRAME_DOMAINS = [
        'youtube.com',
        'youtube-nocookie.com',
        'facebook.com',
        'tiktok.com',
        'telegram.org',
        'soundcloud.com',
        'twitter.com',
        'platform.twitter.com',
        'digi24.ro',
        'news.ro',
        'scribd.com',
        'europalibera.org',
        'newtv.md',
        'realitatea.md',
        'rlive.md',
        'tvrmoldova.md',
        'truthsocial.com',
    ];

    /**
     * Normalize Romanian diacritics from cedilla to comma-below.
     */
    public function normalizeDiacritics(string $text): string
    {
        return str_replace(
            ["\u{015F}", "\u{0163}", "\u{015E}", "\u{0162}"],
            ["\u{0219}", "\u{021B}", "\u{0218}", "\u{021A}"],
            $text,
        );
    }

    /**
     * Full sanitization pipeline for article content.
     */
    public function sanitize(string $html): string
    {
        if (empty(trim($html))) {
            return '';
        }

        $html = $this->normalizeDiacritics($html);
        $html = $this->removeScriptTags($html);
        $html = $this->removeAdIframes($html);
        $html = $this->removeInlineStyles($html);
        $html = $this->removeEmptyAttributes($html);
        $html = $this->removeClassAttributes($html);
        $html = $this->cleanEmptyParagraphs($html);
        $html = $this->normalizeWhitespace($html);

        return trim($html);
    }

    /**
     * Light sanitization for lead/short text (no iframes expected).
     */
    public function sanitizePlainText(string $text): string
    {
        if (empty(trim($text))) {
            return '';
        }

        $text = $this->normalizeDiacritics($text);
        $text = strip_tags($text, '<strong><em><a><br>');
        $text = $this->normalizeWhitespace($text);

        return trim($text);
    }

    private function removeScriptTags(string $html): string
    {
        return (string) preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $html);
    }

    private function removeAdIframes(string $html): string
    {
        return (string) preg_replace_callback(
            '/<iframe\b[^>]*>[\s\S]*?<\/iframe>/i',
            function (array $match): string {
                $tag = $match[0];

                // Extract src
                if (!preg_match('/src=["\']([^"\']+)["\']/i', $tag, $srcMatch)) {
                    return ''; // No src — remove
                }

                $src = $srcMatch[1];
                $host = parse_url($src, \PHP_URL_HOST) ?? '';

                // Check if it's an ad iframe
                foreach (self::AD_IFRAME_DOMAINS as $adDomain) {
                    if (str_contains($host, $adDomain)) {
                        return ''; // Remove ad iframe
                    }
                }

                // Check if it's an editorial iframe
                foreach (self::EDITORIAL_IFRAME_DOMAINS as $editorialDomain) {
                    if (str_contains($host, $editorialDomain)) {
                        return $tag; // Keep editorial iframe
                    }
                }

                // Unknown iframe domain — remove to be safe
                return '';
            },
            $html,
        );
    }

    private function removeInlineStyles(string $html): string
    {
        return (string) preg_replace('/\s+style\s*=\s*"[^"]*"/i', '', $html);
    }

    private function removeEmptyAttributes(string $html): string
    {
        // Remove id="" (empty IDs from Webflow)
        $html = (string) preg_replace('/\s+id\s*=\s*""/i', '', $html);
        // Remove data-* attributes (Webflow artifacts)
        $html = (string) preg_replace('/\s+data-[\w-]+\s*=\s*"[^"]*"/i', '', $html);

        return $html;
    }

    private function removeClassAttributes(string $html): string
    {
        return (string) preg_replace('/\s+class\s*=\s*"[^"]*"/i', '', $html);
    }

    private function cleanEmptyParagraphs(string $html): string
    {
        // Remove <p></p>, <p> </p>, <p>&nbsp;</p>
        return (string) preg_replace('/<p[^>]*>\s*(&nbsp;)?\s*<\/p>/i', '', $html);
    }

    private function normalizeWhitespace(string $html): string
    {
        // Collapse multiple whitespace (but preserve newlines in pre/code)
        $html = (string) preg_replace('/[ \t]+/', ' ', $html);
        // Remove whitespace between tags
        $html = (string) preg_replace('/>\s+</', '><', $html);
        // But add back newline after block elements for readability
        $html = (string) preg_replace('/(<\/(?:p|h[1-6]|ul|ol|li|blockquote|figure|div|pre)>)/i', "$1\n", $html);

        return $html;
    }

    /**
     * Append video embed to content if video_link is present.
     */
    public function appendVideoEmbed(string $content, string $videoUrl): string
    {
        $videoUrl = trim($videoUrl);
        if (empty($videoUrl)) {
            return $content;
        }

        $embedUrl = $this->normalizeVideoEmbedUrl($videoUrl);
        if ($embedUrl === null) {
            return $content;
        }

        $embed = sprintf(
            '<figure class="video-embed"><iframe src="%s" width="560" height="315" frameborder="0" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe></figure>',
            htmlspecialchars($embedUrl, \ENT_QUOTES, 'UTF-8'),
        );

        return $content . "\n" . $embed;
    }

    private function normalizeVideoEmbedUrl(string $url): ?string
    {
        // YouTube: various URL formats → embed format
        if (preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1];
        }

        // Facebook video
        if (str_contains($url, 'facebook.com')) {
            return 'https://www.facebook.com/plugins/video.php?href=' . urlencode($url);
        }

        return null;
    }
}
