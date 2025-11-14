<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Set HTTP cache headers for public API endpoints
 *
 * This subscriber sets appropriate cache headers for GET requests
 * to enable HTTP caching in Varnish and CDN layers.
 */
final class CacheHeadersSubscriber implements EventSubscriberInterface
{
    private const CACHE_MAX_AGE = 3600;        // 1 hour browser cache
    private const CACHE_SHARED_MAX_AGE = 7200; // 2 hours CDN/proxy cache

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -256], // Run last, after all other subscribers
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        // Only set cache headers for successful GET/HEAD requests
        if (!in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return;
        }

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            return;
        }

        // Only cache API endpoints (not profiler, etc.)
        $path = $request->getPathInfo();
        if (!str_starts_with($path, '/api/')) {
            return;
        }

        // Skip authenticated requests (keep them private)
        if ($request->headers->has('Authorization')) {
            $response->headers->set('Cache-Control', 'private, no-cache, no-store, must-revalidate');
            return;
        }

        // Set public cache headers
        $response->setPublic();
        $response->setMaxAge(self::CACHE_MAX_AGE);
        $response->setSharedMaxAge(self::CACHE_SHARED_MAX_AGE);

        // Add Vary headers for proper caching
        $response->setVary([
            'Content-Type',
            'Accept-Language',
            'Origin',
        ]);

        // Add ETag for conditional requests
        if (!$response->headers->has('ETag')) {
            $etag = md5($response->getContent() ?: '');
            $response->setETag($etag);
        }

        // Set Last-Modified if not present
        if (!$response->headers->has('Last-Modified')) {
            $response->setLastModified(new \DateTime());
        }
    }
}
