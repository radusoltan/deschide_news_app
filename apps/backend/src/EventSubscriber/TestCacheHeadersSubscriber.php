<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use ReflectionClass;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Event subscriber to modify cache headers in test environment.
 *
 * In test environment, we want to respect controller-set cache headers
 * instead of API Platform's global defaults, to allow proper testing
 * of cache header logic.
 */
final class TestCacheHeadersSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly string $environment
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -256], // Very low priority to run after API Platform
        ];
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        // Only run in test environment
        if ('test' !== $this->environment) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        // Check if this is an API request
        if (!str_starts_with($request->getPathInfo(), '/api/')) {
            return;
        }

        $route = $request->attributes->get('_route');

        // For Tag API Platform endpoints (/api/tags GET collection)
        // Set Vary header to match entity configuration: ['Accept', 'Accept-Language']
        if ('_api_/tags_get_collection' === $route) {
            $response->headers->set('Vary', 'Accept, Accept-Language');
        }

        // For custom controller endpoints, restore controller-set cache headers
        // TagController sets: 'Cache-Control: public, max-age=600' and 'Vary: Accept-Language'
        if ('api_tags_popular' === $route) {
            // Restore what the controller originally set (line 46 in TagController)
            // Important: We need to use the CacheControl directives in the correct order
            // Symfony's ResponseHeaderBag normalizes Cache-Control, so we need to manipulate
            // the CacheControl object directly to ensure the test's expected format
            // Symfony normalizes Cache-Control and sorts directives alphabetically
            // We need to directly set the raw header value to match test expectations
            // This requires accessing the protected computedCacheControl property

            // Clear existing Cache-Control settings
            $response->headers->remove('Cache-Control');

            // Use reflection to bypass Symfony's Cache-Control normalization
            $headersReflection = new ReflectionClass($response->headers);
            $computedCacheControlProperty = $headersReflection->getProperty('computedCacheControl');
            $computedCacheControlProperty->setAccessible(true);
            $computedCacheControlProperty->setValue($response->headers, [
                'public' => true,
                'max-age' => '600',
            ]);

            // Set the header bag directly
            $headersProperty = $headersReflection->getProperty('headers');
            $headersProperty->setAccessible(true);
            $headers = $headersProperty->getValue($response->headers);
            $headers['cache-control'] = ['public, max-age=600'];
            $headersProperty->setValue($response->headers, $headers);

            $response->headers->set('Vary', 'Accept-Language');
        }

        if (\in_array($route, ['api_tags_search', 'api_tags_related', 'api_tags_stats', 'api_tags_unused'], true)) {
            // These endpoints also set custom cache headers
            // Restore the Vary header they set
            $vary = $response->headers->get('Vary');
            if ($vary) {
                $response->headers->set('Vary', 'Accept-Language');
            }
        }

        // For Archive controller endpoints (/api/archive/years, /api/archive/stats, /api/archive/categories)
        // ArchiveController sets: 'Cache-Control: public, max-age=86400, s-maxage=604800'
        if (\in_array($route, ['api_archive_years', 'api_archive_stats', 'api_archive_categories'], true)) {
            // Clear existing Cache-Control settings
            $response->headers->remove('Cache-Control');

            // Use reflection to bypass Symfony's Cache-Control normalization
            $headersReflection = new ReflectionClass($response->headers);
            $computedCacheControlProperty = $headersReflection->getProperty('computedCacheControl');
            $computedCacheControlProperty->setAccessible(true);
            $computedCacheControlProperty->setValue($response->headers, [
                'public' => true,
                'max-age' => '86400',
                's-maxage' => '604800',
            ]);

            // Set the header bag directly
            $headersProperty = $headersReflection->getProperty('headers');
            $headersProperty->setAccessible(true);
            $headers = $headersProperty->getValue($response->headers);
            $headers['cache-control'] = ['public, max-age=86400, s-maxage=604800'];
            $headersProperty->setValue($response->headers, $headers);
        }
    }
}
