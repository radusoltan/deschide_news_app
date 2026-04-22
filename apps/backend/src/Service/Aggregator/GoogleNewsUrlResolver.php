<?php

declare(strict_types=1);

namespace App\Service\Aggregator;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Resolves Google News redirect URLs to real article URLs.
 *
 * Google News RSS feeds return URLs like:
 *   https://news.google.com/rss/articles/CBMi...
 *
 * Strategy (in order):
 * 1. Base64 decode: old-format URLs embed the destination URL directly
 *    in a protobuf payload (field 4) — extract via regex (no HTTP needed).
 * 2. HTTP redirect: follow redirects and parse the consent page for the
 *    real URL (works when Google serves a 302 to the destination).
 *
 * New-format URLs (2024+, with encrypted AU_ prefix payload) cannot be
 * decoded offline. For those, the resolver returns null and the editor
 * can use the "Fetch Content" button in Press Queue as a fallback.
 */
readonly class GoogleNewsUrlResolver
{
    private const TIMEOUT = 5;
    private const MAX_REDIRECTS = 5;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {}

    /**
     * Attempt to resolve a Google News URL to the real article URL.
     *
     * Returns the resolved URL, or null if resolution fails.
     * Non-Google URLs are passed through unchanged.
     */
    public function resolveUrl(string $googleNewsUrl): ?string
    {
        if (!$this->isGoogleNewsUrl($googleNewsUrl)) {
            return $googleNewsUrl;
        }

        // Strategy 1: decode URL from base64-encoded protobuf path segment (fast, no HTTP)
        $decoded = $this->decodeFromPath($googleNewsUrl);
        if ($decoded !== null) {
            return $decoded;
        }

        // Strategy 2: follow HTTP redirects (handles consent page, older redirect chains)
        return $this->followRedirects($googleNewsUrl);
    }

    /**
     * Extract the article URL from the base64url-encoded protobuf payload
     * embedded in the Google News URL path.
     *
     * Old-format Google News URLs (pre-2024) encode the destination URL
     * directly in a protobuf message (field 1 = version, field 4 = URL string).
     * New-format URLs use an encrypted `AU_`-prefixed payload that cannot
     * be decoded offline — this method returns null for those.
     */
    private function decodeFromPath(string $url): ?string
    {
        // Extract the base64url segment after /articles/
        if (!preg_match('#/articles/([a-zA-Z0-9\-_]+)#', $url, $matches)) {
            return null;
        }

        $segment = $matches[1];

        // Base64url decode (replace -_ with +/, add padding)
        $decoded = base64_decode(strtr($segment, '-_', '+/'), true);
        if ($decoded === false || $decoded === '') {
            return null;
        }

        // Search for an https:// or http:// URL in the decoded binary payload.
        // In old-format protobuf messages, the URL is stored as a plain string
        // in field 4. The regex naturally stops at non-URL bytes (protobuf control chars).
        if (preg_match('/(https?:\/\/[a-zA-Z0-9\-._~:\/?&=+%#@!\[\];,\'()*]+)/', $decoded, $urlMatch)) {
            $realUrl = $urlMatch[1];

            // Reject if it points back to Google News (not a real resolution)
            if ($this->isGoogleNewsUrl($realUrl)) {
                return null;
            }

            $this->logger->info('GoogleNewsUrlResolver: decoded from path', [
                'to' => mb_substr($realUrl, 0, 100),
            ]);

            return $realUrl;
        }

        return null;
    }

    /**
     * Follow HTTP redirects to find the final destination URL.
     *
     * Handles cases where Google serves a 302 redirect to the real article,
     * or redirects to a consent page that contains the real URL in query params.
     */
    private function followRedirects(string $googleNewsUrl): ?string
    {
        try {
            $response = $this->httpClient->request('GET', $googleNewsUrl, [
                'timeout' => self::TIMEOUT,
                'max_redirects' => self::MAX_REDIRECTS,
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (compatible; DeschideNewsBot/1.0)',
                    'Accept' => 'text/html',
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode >= 400) {
                $this->logger->warning('GoogleNewsUrlResolver: HTTP {status}', [
                    'status' => $statusCode,
                    'url' => mb_substr($googleNewsUrl, 0, 100),
                ]);

                return null;
            }

            $finalUrl = $response->getInfo('url') ?? $googleNewsUrl;

            $finalHost = parse_url($finalUrl, \PHP_URL_HOST) ?? '';

            // Check consent page: Google sometimes redirects to consent.google.com
            // with the real URL in the "continue" query parameter
            if (str_contains($finalHost, 'consent.google.com')) {
                $query = parse_url($finalUrl, \PHP_URL_QUERY);
                if ($query !== null) {
                    parse_str($query, $params);
                    $continueUrl = $params['continue'] ?? null;
                    if ($continueUrl !== null && !$this->isGoogleUrl($continueUrl)) {
                        return $continueUrl;
                    }
                }

                return null;
            }

            // Check if we landed on the real article (not Google anymore)
            if (!$this->isGoogleUrl($finalUrl) && $finalUrl !== $googleNewsUrl) {
                $this->logger->info('GoogleNewsUrlResolver: resolved via HTTP redirect', [
                    'to' => mb_substr($finalUrl, 0, 100),
                ]);

                return $finalUrl;
            }

            return null;
        } catch (\Throwable $e) {
            $this->logger->warning('GoogleNewsUrlResolver: HTTP resolution failed', [
                'url' => mb_substr($googleNewsUrl, 0, 100),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function isGoogleNewsUrl(string $url): bool
    {
        $host = parse_url($url, \PHP_URL_HOST);

        return $host !== null && str_contains($host, 'news.google.com');
    }

    /**
     * Check if URL belongs to any Google domain (news, consent, etc.).
     */
    private function isGoogleUrl(string $url): bool
    {
        $host = parse_url($url, \PHP_URL_HOST);

        return $host !== null && str_contains($host, 'google.com');
    }
}
