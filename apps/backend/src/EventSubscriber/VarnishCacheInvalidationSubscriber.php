<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Article;
use App\Entity\Category;
use App\Service\VarnishCacheService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Automatically invalidate Varnish cache when content changes.
 *
 * This subscriber listens to API Platform POST/PUT/PATCH/DELETE operations
 * and invalidates the relevant Varnish cache entries.
 */
final class VarnishCacheInvalidationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly VarnishCacheService $varnishCache
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
                $this->varnishCache->purgeUrl("/api/articles/{$articleId}");
            }

            // Purge all article collections (pagination, filters)
            $this->varnishCache->banPattern('/api/articles\?.*');
            $this->varnishCache->banPattern('/api/important_articles_lists.*');
        } elseif ($method === 'POST') {
            // On create, only purge collections (new article won't have a cached page yet)
            $this->varnishCache->banPattern('/api/articles\?.*');
            $this->varnishCache->banPattern('/api/important_articles_lists.*');
        } else {
            // On update (PUT/PATCH), purge specific article and collections
            if ($articleId) {
                $this->varnishCache->purgeArticle($articleId);
            } else {
                // Fallback: purge all articles if we don't have ID
                $this->varnishCache->banPattern('/api/articles.*');
            }
        }
    }

    private function invalidateCategoryCache(?int $categoryId, string $method): void
    {
        if ($method === 'DELETE') {
            // On delete, purge the specific category
            if ($categoryId) {
                $this->varnishCache->purgeUrl("/api/categories/{$categoryId}");
            }

            // Purge all category collections
            $this->varnishCache->banPattern('/api/categories\?.*');

            // Purge articles (category relationship changed)
            $this->varnishCache->banPattern('/api/articles.*');
        } elseif ($method === 'POST') {
            // On create, purge category collections
            $this->varnishCache->banPattern('/api/categories\?.*');
        } else {
            // On update (PUT/PATCH), purge specific category and related data
            if ($categoryId) {
                $this->varnishCache->purgeCategory($categoryId);
            } else {
                // Fallback: purge all categories
                $this->varnishCache->banPattern('/api/categories.*');
            }
        }
    }
}
