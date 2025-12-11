<?php

declare(strict_types=1);

namespace App\Service;

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service for invalidating Cloudflare CDN cache.
 *
 * Usage:
 *   $this->cloudflareCache->purgeUrl('https://api.deschide.md/api/articles/123');
 *   $this->cloudflareCache->purgeByPrefix('/api/articles');
 *   $this->cloudflareCache->purgeAll();
 */
final class CloudflareCacheService
{
    private bool $enabled;

    private string $apiToken;

    private string $zoneId;

    private string $apiBaseUrl = 'https://api.cloudflare.com/client/v4';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        string $cloudflareApiToken = '',
        string $cloudflareZoneId = '',
        bool $cloudflareEnabled = false
    ) {
        $this->apiToken = $cloudflareApiToken;
        $this->zoneId = $cloudflareZoneId;
        $this->enabled = $cloudflareEnabled && !empty($cloudflareApiToken) && !empty($cloudflareZoneId);
    }

    /**
     * Purge specific URLs from Cloudflare cache.
     *
     * @param array<string> $urls Full URLs to purge (e.g., ['https://api.deschide.md/api/articles/123'])
     *
     * @return bool True if purge was successful
     */
    public function purgeUrls(array $urls): bool
    {
        if (!$this->enabled) {
            $this->logger->debug('Cloudflare cache purge skipped (disabled)', ['urls' => $urls]);

            return false;
        }

        if (empty($urls)) {
            return true;
        }

        try {
            $response = $this->httpClient->request('POST', "{$this->apiBaseUrl}/zones/{$this->zoneId}/purge_cache", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'files' => $urls,
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray();

            if ($data['success'] ?? false) {
                $this->logger->info('Cloudflare cache purged', [
                    'urls' => $urls,
                    'count' => \count($urls),
                ]);

                return true;
            }

            $this->logger->warning('Cloudflare cache purge failed', [
                'urls' => $urls,
                'errors' => $data['errors'] ?? [],
            ]);

            return false;
        } catch (Exception $e) {
            $this->logger->error('Cloudflare cache purge error', [
                'urls' => $urls,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Purge a single URL from Cloudflare cache.
     *
     * @param string $url Full URL to purge (e.g., 'https://api.deschide.md/api/articles/123')
     *
     * @return bool True if purge was successful
     */
    public function purgeUrl(string $url): bool
    {
        return $this->purgeUrls([$url]);
    }

    /**
     * Purge cache by URL prefix (requires Enterprise plan).
     *
     * @param string $prefix URL prefix to purge (e.g., 'https://api.deschide.md/api/articles')
     *
     * @return bool True if purge was successful
     */
    public function purgeByPrefix(string $prefix): bool
    {
        if (!$this->enabled) {
            $this->logger->debug('Cloudflare cache purge by prefix skipped (disabled)', ['prefix' => $prefix]);

            return false;
        }

        try {
            $response = $this->httpClient->request('POST', "{$this->apiBaseUrl}/zones/{$this->zoneId}/purge_cache", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'prefixes' => [$prefix],
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray();

            if ($data['success'] ?? false) {
                $this->logger->info('Cloudflare cache purged by prefix', ['prefix' => $prefix]);

                return true;
            }

            // If prefix purge fails (non-Enterprise), fall back to purging all
            $this->logger->warning('Cloudflare prefix purge requires Enterprise plan, falling back to purge all');

            return $this->purgeAll();
        } catch (Exception $e) {
            $this->logger->error('Cloudflare cache purge by prefix error', [
                'prefix' => $prefix,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Purge by tags (requires Enterprise plan).
     *
     * @param array<string> $tags Cache tags to purge
     *
     * @return bool True if purge was successful
     */
    public function purgeByTags(array $tags): bool
    {
        if (!$this->enabled) {
            $this->logger->debug('Cloudflare cache purge by tags skipped (disabled)', ['tags' => $tags]);

            return false;
        }

        if (empty($tags)) {
            return true;
        }

        try {
            $response = $this->httpClient->request('POST', "{$this->apiBaseUrl}/zones/{$this->zoneId}/purge_cache", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'tags' => $tags,
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray();

            if ($data['success'] ?? false) {
                $this->logger->info('Cloudflare cache purged by tags', ['tags' => $tags]);

                return true;
            }

            $this->logger->warning('Cloudflare cache purge by tags failed (requires Enterprise plan)', [
                'tags' => $tags,
                'errors' => $data['errors'] ?? [],
            ]);

            return false;
        } catch (Exception $e) {
            $this->logger->error('Cloudflare cache purge by tags error', [
                'tags' => $tags,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Purge all cached content (use sparingly!).
     *
     * WARNING: This purges EVERYTHING from Cloudflare cache.
     * Only use when absolutely necessary (full deployment, critical bug fix)
     *
     * @return bool True if purge was successful
     */
    public function purgeAll(): bool
    {
        if (!$this->enabled) {
            $this->logger->debug('Cloudflare cache purge all skipped (disabled)');

            return false;
        }

        try {
            $response = $this->httpClient->request('POST', "{$this->apiBaseUrl}/zones/{$this->zoneId}/purge_cache", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiToken}",
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'purge_everything' => true,
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray();

            if ($data['success'] ?? false) {
                $this->logger->warning('Cloudflare cache: Purged EVERYTHING (all zones, all files)');

                return true;
            }

            $this->logger->error('Cloudflare cache purge all failed', [
                'errors' => $data['errors'] ?? [],
            ]);

            return false;
        } catch (Exception $e) {
            $this->logger->error('Cloudflare cache purge all error', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Purge article-related URLs.
     *
     * @param int $articleId Article ID
     * @param string $domain Base domain (e.g., 'https://api.deschide.md')
     * @param array<string> $locales Locales to purge (default: ro, en, ru)
     */
    public function purgeArticle(int $articleId, string $domain, array $locales = ['ro', 'en', 'ru']): void
    {
        $urls = [];

        // Purge specific article in all languages
        foreach ($locales as $locale) {
            $urls[] = "{$domain}/api/articles/{$articleId}?locale={$locale}";
            $urls[] = "{$domain}/api/articles/{$articleId}"; // Without locale param
        }

        $this->purgeUrls($urls);

        // Note: We can't easily purge article collections (pagination)
        // For non-Enterprise plans, we'd need to purge all or use webhooks
        // For Enterprise plans, use purgeByPrefix() or purgeByTags()
    }

    /**
     * Purge category-related URLs.
     *
     * @param int $categoryId Category ID
     * @param string $domain Base domain
     * @param array<string> $locales Locales to purge
     */
    public function purgeCategory(int $categoryId, string $domain, array $locales = ['ro', 'en', 'ru']): void
    {
        $urls = [];

        // Purge specific category in all languages
        foreach ($locales as $locale) {
            $urls[] = "{$domain}/api/categories/{$categoryId}?locale={$locale}";
            $urls[] = "{$domain}/api/categories/{$categoryId}";
        }

        $this->purgeUrls($urls);

        // For collections filtered by category, use purgeAll() or Enterprise features
    }

    /**
     * Get cache analytics (optional, requires API calls).
     *
     * @return array<string, mixed> Cache statistics
     */
    public function getCacheAnalytics(): array
    {
        if (!$this->enabled) {
            return [];
        }

        try {
            // Get cache analytics for the last 24 hours
            $response = $this->httpClient->request('GET', "{$this->apiBaseUrl}/zones/{$this->zoneId}/analytics/dashboard", [
                'headers' => [
                    'Authorization' => "Bearer {$this->apiToken}",
                ],
                'query' => [
                    'since' => -1440, // Last 24 hours in minutes
                    'until' => 0,
                ],
                'timeout' => 10,
            ]);

            $data = $response->toArray();

            if ($data['success'] ?? false) {
                return $data['result'] ?? [];
            }

            return [];
        } catch (Exception $e) {
            $this->logger->error('Cloudflare analytics fetch error', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Check if Cloudflare is enabled.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Enable Cloudflare cache operations.
     */
    public function enable(): void
    {
        $this->enabled = true;
        $this->logger->info('Cloudflare cache operations enabled');
    }

    /**
     * Disable Cloudflare cache operations.
     */
    public function disable(): void
    {
        $this->enabled = false;
        $this->logger->info('Cloudflare cache operations disabled');
    }
}
