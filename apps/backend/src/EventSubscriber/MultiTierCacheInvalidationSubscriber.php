<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Article;
use App\Entity\Category;
use App\Service\CloudflareCacheService;
use App\Service\VarnishCacheService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Automatically invalidate multi-tier cache (Cloudflare + Varnish) when content changes.
 *
 * Cache invalidation strategy:
 * 1. Cloudflare Edge (global CDN)
 * 2. Varnish Origin (local HTTP cache)
 * 3. Both are invalidated simultaneously for consistency
 */
final class MultiTierCacheInvalidationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly VarnishCacheService $varnishCache,
        private readonly CloudflareCacheService $cloudflareCache,
        private readonly string $apiDomain = 'https://api.deschide.md'
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -10], // After API Platform
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        // Only invalidate on successful mutations (2xx status codes)
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return;
        }

        // Only invalidate on write operations
        $method = $request->getMethod();
        if (!\in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        // Get the resource class from API Platform context
        $attributes = $request->attributes;
        $resourceClass = $attributes->get('_api_resource_class');

        if (!$resourceClass) {
            return;
        }

        // Get resource ID if available (cast to int for numeric IDs)
        $resourceId = $attributes->get('id');
        if ($resourceId !== null && is_numeric($resourceId)) {
            $resourceId = (int) $resourceId;
        }

        // Invalidate cache based on resource type
        $this->invalidateResourceCache($resourceClass, $resourceId, $method);
    }

    private function invalidateResourceCache(string $resourceClass, mixed $resourceId, string $method): void
    {
        match ($resourceClass) {
            Article::class => $this->invalidateArticleCache($resourceId, $method),
            Category::class => $this->invalidateCategoryCache($resourceId, $method),
            // Add more resource types as needed
            default => null,
        };
    }

    private function invalidateArticleCache(?int $articleId, string $method): void
    {
        if ($method === 'DELETE') {
            // On delete, purge the specific article
            if ($articleId) {
                // Varnish: Purge specific article
                $this->varnishCache->purgeUrl("/api/articles/{$articleId}");

                // Cloudflare: Purge article in all languages
                $this->cloudflareCache->purgeArticle($articleId, $this->apiDomain);
            }

            // Purge all article collections (pagination, filters)
            $this->varnishCache->banPattern('/api/articles\?.*');
            $this->varnishCache->banPattern('/api/important_articles_lists.*');

            // Cloudflare: For non-Enterprise, we can't purge patterns
            // Option 1: Purge known collection URLs (first few pages)
            $this->purgeArticleCollections();
        } elseif ($method === 'POST') {
            // On create, only purge collections (new article won't have a cached page yet)
            $this->varnishCache->banPattern('/api/articles\?.*');
            $this->varnishCache->banPattern('/api/important_articles_lists.*');

            // Cloudflare: Purge collection pages
            $this->purgeArticleCollections();
        } else {
            // On update (PUT/PATCH), purge specific article and collections
            if ($articleId) {
                // Varnish
                $this->varnishCache->purgeArticle($articleId);

                // Cloudflare
                $this->cloudflareCache->purgeArticle($articleId, $this->apiDomain);
            }

            // Collections
            $this->varnishCache->banPattern('/api/articles\?.*');
            $this->purgeArticleCollections();
        }
    }

    private function invalidateCategoryCache(?int $categoryId, string $method): void
    {
        if ($method === 'DELETE') {
            // On delete, purge the specific category
            if ($categoryId) {
                // Varnish
                $this->varnishCache->purgeUrl("/api/categories/{$categoryId}");

                // Cloudflare
                $this->cloudflareCache->purgeCategory($categoryId, $this->apiDomain);
            }

            // Purge all category collections
            $this->varnishCache->banPattern('/api/categories\?.*');

            // Purge articles (category relationship changed)
            $this->varnishCache->banPattern('/api/articles.*');
            $this->purgeArticleCollections();
        } elseif ($method === 'POST') {
            // On create, purge category collections
            $this->varnishCache->banPattern('/api/categories\?.*');
            $this->purgeCategoryCollections();
        } else {
            // On update (PUT/PATCH), purge specific category and related data
            if ($categoryId) {
                // Varnish
                $this->varnishCache->purgeCategory($categoryId);

                // Cloudflare
                $this->cloudflareCache->purgeCategory($categoryId, $this->apiDomain);
            }

            // Related data
            $this->varnishCache->banPattern('/api/categories\?.*');
            $this->purgeCategoryCollections();
        }
    }

    /**
     * Purge article collection pages (first 10 pages, common filters)
     * This is a workaround for non-Enterprise Cloudflare plans that don't support prefix purging.
     */
    private function purgeArticleCollections(): void
    {
        if (!$this->cloudflareCache->isEnabled()) {
            return;
        }

        $urls = [];
        $locales = ['ro', 'en', 'ru'];
        $itemsPerPage = [5, 10, 20, 30];

        // Purge first 3 pages for common pagination sizes
        foreach ($locales as $locale) {
            foreach ($itemsPerPage as $ipp) {
                for ($page = 1; $page <= 3; ++$page) {
                    $urls[] = "{$this->apiDomain}/api/articles?page={$page}&itemsPerPage={$ipp}&locale={$locale}";
                }
            }

            // Also purge default (no pagination params)
            $urls[] = "{$this->apiDomain}/api/articles?locale={$locale}";
        }

        // Purge important articles lists
        foreach ($locales as $locale) {
            $urls[] = "{$this->apiDomain}/api/important_articles_lists?locale={$locale}";
        }

        // Batch purge (Cloudflare allows up to 30 URLs per request)
        $batches = array_chunk($urls, 30);
        foreach ($batches as $batch) {
            $this->cloudflareCache->purgeUrls($batch);
        }
    }

    /**
     * Purge category collection pages.
     */
    private function purgeCategoryCollections(): void
    {
        if (!$this->cloudflareCache->isEnabled()) {
            return;
        }

        $urls = [];
        $locales = ['ro', 'en', 'ru'];

        foreach ($locales as $locale) {
            $urls[] = "{$this->apiDomain}/api/categories?locale={$locale}";
        }

        $this->cloudflareCache->purgeUrls($urls);
    }
}
