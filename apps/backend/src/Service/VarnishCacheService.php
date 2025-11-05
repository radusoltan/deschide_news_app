<?php

declare(strict_types=1);

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service for invalidating Varnish HTTP cache
 *
 * Usage:
 *   $this->varnishCache->purgeUrl('/api/articles/123');
 *   $this->varnishCache->banPattern('/api/articles.*');
 */
final class VarnishCacheService
{
    private bool $enabled;
    private string $varnishHost;
    private int $varnishPort;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        string $varnishHost = '127.0.0.1',
        int $varnishPort = 6081,
        bool $varnishEnabled = true
    ) {
        $this->varnishHost = $varnishHost;
        $this->varnishPort = $varnishPort;
        $this->enabled = $varnishEnabled;
    }

    /**
     * Purge a specific URL from Varnish cache
     *
     * @param string $url URL path to purge (e.g., '/api/articles/123')
     * @return bool True if purge was successful
     */
    public function purgeUrl(string $url): bool
    {
        if (!$this->enabled) {
            $this->logger->debug('Varnish cache purge skipped (disabled)', ['url' => $url]);
            return false;
        }

        try {
            $varnishUrl = sprintf('http://%s:%d%s', $this->varnishHost, $this->varnishPort, $url);

            $response = $this->httpClient->request('PURGE', $varnishUrl, [
                'timeout' => 2,
                'headers' => [
                    'Host' => '127.0.0.1',
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode === 200) {
                $this->logger->info('Varnish cache purged', [
                    'url' => $url,
                    'varnish_url' => $varnishUrl,
                ]);
                return true;
            }

            $this->logger->warning('Varnish cache purge failed', [
                'url' => $url,
                'status_code' => $statusCode,
            ]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Varnish cache purge error', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Ban URLs matching a pattern (bulk purge)
     *
     * @param string $pattern URL pattern (regex) to ban (e.g., '/api/articles.*')
     * @return bool True if ban was successful
     */
    public function banPattern(string $pattern): bool
    {
        if (!$this->enabled) {
            $this->logger->debug('Varnish cache ban skipped (disabled)', ['pattern' => $pattern]);
            return false;
        }

        try {
            $varnishUrl = sprintf('http://%s:%d%s', $this->varnishHost, $this->varnishPort, $pattern);

            $response = $this->httpClient->request('BAN', $varnishUrl, [
                'timeout' => 2,
                'headers' => [
                    'Host' => '127.0.0.1',
                ],
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode === 200) {
                $this->logger->info('Varnish cache banned', [
                    'pattern' => $pattern,
                    'varnish_url' => $varnishUrl,
                ]);
                return true;
            }

            $this->logger->warning('Varnish cache ban failed', [
                'pattern' => $pattern,
                'status_code' => $statusCode,
            ]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Varnish cache ban error', [
                'pattern' => $pattern,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Purge an article and related collections
     *
     * @param int $articleId Article ID
     */
    public function purgeArticle(int $articleId): void
    {
        // Purge specific article
        $this->purgeUrl("/api/articles/{$articleId}");

        // Purge article collections (all pages, all languages)
        $this->banPattern('/api/articles\?.*');

        // Purge important articles lists
        $this->banPattern('/api/important_articles_lists.*');
    }

    /**
     * Purge a category and related collections
     *
     * @param int $categoryId Category ID
     */
    public function purgeCategory(int $categoryId): void
    {
        // Purge specific category
        $this->purgeUrl("/api/categories/{$categoryId}");

        // Purge category collections
        $this->banPattern('/api/categories\?.*');

        // Purge articles filtered by this category
        $this->banPattern("/api/articles\?.*category.*{$categoryId}.*");
    }

    /**
     * Purge all articles (use sparingly!)
     */
    public function purgeAllArticles(): void
    {
        $this->banPattern('/api/articles.*');
        $this->logger->warning('Varnish cache: Purged ALL articles');
    }

    /**
     * Purge all API endpoints (nuclear option - use only when necessary)
     */
    public function purgeAll(): void
    {
        $this->banPattern('/api/.*');
        $this->logger->warning('Varnish cache: Purged ALL API endpoints');
    }

    /**
     * Check if Varnish is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Enable Varnish cache invalidation
     */
    public function enable(): void
    {
        $this->enabled = true;
        $this->logger->info('Varnish cache invalidation enabled');
    }

    /**
     * Disable Varnish cache invalidation
     */
    public function disable(): void
    {
        $this->enabled = false;
        $this->logger->info('Varnish cache invalidation disabled');
    }
}
