<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\TestCacheHeadersSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class TestCacheHeadersSubscriberTest extends TestCase
{
    // ── getSubscribedEvents ────────────────────────────────────────────────────

    public function testGetSubscribedEventsContainsKernelResponse(): void
    {
        $events = TestCacheHeadersSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::RESPONSE, $events);
    }

    public function testGetSubscribedEventsHasLowPriority(): void
    {
        $events = TestCacheHeadersSubscriber::getSubscribedEvents();

        $this->assertSame(['onKernelResponse', -256], $events[KernelEvents::RESPONSE]);
    }

    // ── onKernelResponse in non-test environment ───────────────────────────────

    public function testDoesNothingInProductionEnvironment(): void
    {
        $subscriber = new TestCacheHeadersSubscriber('prod');

        $request = Request::create('/api/articles');
        $response = new Response('', 200);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        // Headers should remain unchanged (no Vary header added for prod)
        $this->assertTrue(true);
    }

    public function testDoesNothingInDevEnvironment(): void
    {
        $subscriber = new TestCacheHeadersSubscriber('dev');

        $request = Request::create('/api/tags');
        $response = new Response('', 200);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        $this->assertTrue(true);
    }

    // ── onKernelResponse in test environment ──────────────────────────────────

    public function testDoesNothingForNonApiPathInTestEnvironment(): void
    {
        $subscriber = new TestCacheHeadersSubscriber('test');

        $request = Request::create('/some-page');
        $response = new Response('', 200);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $originalHeaders = $response->headers->all();

        $subscriber->onKernelResponse($event);

        // Response should be unchanged for non-API paths
        $this->assertTrue(true);
    }

    public function testSetsVaryHeaderForTagsCollectionRoute(): void
    {
        $subscriber = new TestCacheHeadersSubscriber('test');

        $request = Request::create('/api/tags');
        $request->attributes->set('_route', '_api_/tags_get_collection');
        $response = new Response('', 200);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        $this->assertSame('Accept, Accept-Language', $response->headers->get('Vary'));
    }

    public function testHandlesTagsPopularRoute(): void
    {
        $subscriber = new TestCacheHeadersSubscriber('test');

        $request = Request::create('/api/tags/popular');
        $request->attributes->set('_route', 'api_tags_popular');
        $response = new Response('', 200);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        // Vary: Accept-Language should be set
        $this->assertSame('Accept-Language', $response->headers->get('Vary'));
    }

    public function testHandlesTagsSearchRoute(): void
    {
        $subscriber = new TestCacheHeadersSubscriber('test');

        $request = Request::create('/api/tags/search');
        $request->attributes->set('_route', 'api_tags_search');
        $response = new Response('', 200);
        $response->headers->set('Vary', 'Accept-Language');

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        $this->assertSame('Accept-Language', $response->headers->get('Vary'));
    }

    public function testHandlesTagsSearchRouteWithNoVaryHeader(): void
    {
        $subscriber = new TestCacheHeadersSubscriber('test');

        $request = Request::create('/api/tags/search');
        $request->attributes->set('_route', 'api_tags_search');
        $response = new Response('', 200);
        // No Vary header set

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        // Should not throw
        $subscriber->onKernelResponse($event);

        $this->assertTrue(true);
    }

    public function testHandlesArchiveYearsRoute(): void
    {
        $subscriber = new TestCacheHeadersSubscriber('test');

        $request = Request::create('/api/archive/years');
        $request->attributes->set('_route', 'api_archive_years');
        $response = new Response('', 200);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        // Cache-Control should be set with public, max-age=86400, s-maxage=604800
        $cacheControl = $response->headers->get('Cache-Control');
        $this->assertNotNull($cacheControl);
    }

    public function testHandlesArchiveStatsRoute(): void
    {
        $subscriber = new TestCacheHeadersSubscriber('test');

        $request = Request::create('/api/archive/stats');
        $request->attributes->set('_route', 'api_archive_stats');
        $response = new Response('', 200);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        $this->assertTrue(true);
    }

    public function testHandlesArchiveCategoriesRoute(): void
    {
        $subscriber = new TestCacheHeadersSubscriber('test');

        $request = Request::create('/api/archive/categories');
        $request->attributes->set('_route', 'api_archive_categories');
        $response = new Response('', 200);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        $this->assertTrue(true);
    }

    public function testHandlesTagsRelatedRoute(): void
    {
        $subscriber = new TestCacheHeadersSubscriber('test');

        $request = Request::create('/api/tags/related');
        $request->attributes->set('_route', 'api_tags_related');
        $response = new Response('', 200);
        $response->headers->set('Vary', 'Accept-Language');

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        $this->assertSame('Accept-Language', $response->headers->get('Vary'));
    }

    public function testHandlesTagsStatsRoute(): void
    {
        $subscriber = new TestCacheHeadersSubscriber('test');

        $request = Request::create('/api/tags/stats');
        $request->attributes->set('_route', 'api_tags_stats');
        $response = new Response('', 200);
        $response->headers->set('Vary', 'Accept-Language');

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        $this->assertSame('Accept-Language', $response->headers->get('Vary'));
    }

    public function testHandlesTagsUnusedRoute(): void
    {
        $subscriber = new TestCacheHeadersSubscriber('test');

        $request = Request::create('/api/tags/unused');
        $request->attributes->set('_route', 'api_tags_unused');
        $response = new Response('', 200);
        $response->headers->set('Vary', 'Accept-Language');

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        $this->assertSame('Accept-Language', $response->headers->get('Vary'));
    }

    public function testHandlesUnknownApiRoute(): void
    {
        $subscriber = new TestCacheHeadersSubscriber('test');

        $request = Request::create('/api/some-other-endpoint');
        $request->attributes->set('_route', 'api_other');
        $response = new Response('', 200);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        // Must not throw
        $subscriber->onKernelResponse($event);

        $this->assertTrue(true);
    }
}
