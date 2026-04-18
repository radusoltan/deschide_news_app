<?php

declare(strict_types=1);

namespace App\Service\Editorial\Monitor;

/**
 * Canonicalises a URL for stable per-source deduplication (Sprint 53 T53.5).
 *
 * Strips common tracking-parameter families (utm_*, fbclid, gclid, ref/ref_src,
 * _ga, mc_cid/mc_eid), sorts the remaining query string alphabetically, lowercases
 * the host, drops the default port, and removes the trailing slash from an
 * otherwise-empty path. The output is suitable for SHA-256 hashing via
 * {@see \App\Service\ContentHasher} as part of SourceSignal.raw_content_hash.
 *
 * Invalid URLs are returned verbatim — the normaliser is best-effort and must
 * not raise on malformed input (monitor pipelines treat every FeedItem as
 * untrusted).
 */
final class UrlNormalizer
{
    /**
     * Query-parameter name prefixes/values stripped on every call.
     *
     * @var list<string>
     */
    private const TRACKING_PARAMS = [
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'utm_id', 'utm_name', 'utm_reader',
        'fbclid', 'gclid', 'dclid', 'msclkid', 'yclid',
        'ref', 'ref_src', 'ref_url',
        '_ga', '_gl',
        'mc_cid', 'mc_eid',
        'icid', 'sref',
    ];

    public function normalize(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['host'])) {
            return $url;
        }

        $scheme = strtolower($parts['scheme'] ?? 'https');
        $host = strtolower($parts['host']);

        $port = null;
        if (isset($parts['port'])) {
            $defaultPort = $scheme === 'https' ? 443 : ($scheme === 'http' ? 80 : null);
            if ($parts['port'] !== $defaultPort) {
                $port = $parts['port'];
            }
        }

        $path = $parts['path'] ?? '';
        if ($path === '/') {
            $path = '';
        }

        $query = '';
        if (isset($parts['query']) && $parts['query'] !== '') {
            parse_str($parts['query'], $params);
            $filtered = array_filter(
                $params,
                static fn (mixed $value, string $key): bool => !self::isTracking($key),
                \ARRAY_FILTER_USE_BOTH,
            );
            if ($filtered !== []) {
                ksort($filtered);
                $query = '?' . http_build_query($filtered);
            }
        }

        $fragment = isset($parts['fragment']) ? '#' . $parts['fragment'] : '';

        $userInfo = '';
        if (isset($parts['user'])) {
            $userInfo = $parts['user'];
            if (isset($parts['pass'])) {
                $userInfo .= ':' . $parts['pass'];
            }
            $userInfo .= '@';
        }

        return sprintf(
            '%s://%s%s%s%s%s%s',
            $scheme,
            $userInfo,
            $host,
            $port !== null ? ':' . $port : '',
            $path,
            $query,
            $fragment,
        );
    }

    private static function isTracking(string $key): bool
    {
        $normalized = strtolower($key);

        return \in_array($normalized, self::TRACKING_PARAMS, true);
    }
}
