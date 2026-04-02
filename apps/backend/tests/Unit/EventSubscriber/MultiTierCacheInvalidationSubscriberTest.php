<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\Article;
use App\Entity\Category;
use App\EventSubscriber\MultiTierCacheInvalidationSubscriber;
use App\Service\CloudflareCacheService;
use App\Service\VarnishCacheService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class MultiTierCacheInvalidationSubscriberTest extends TestCase
{
    /**
     * CloudflareCacheService is final, so we create a real disabled instance.
     */
    private function buildDisabledCloudflare(): CloudflareCacheService
    {
        return new CloudflareCacheService(
            $this->createStub(HttpClientInterface::class),
            new NullLogger(),
            '', // no API token
            '', // no zone ID
            false // disabled
        );
    }

    public function testGetSubscribedEventsReturnsKernelResponse(): void
    {
        $events = MultiTierCacheInvalidationSubscriber::getSubscribedEvents();
        $this->assertArrayHasKey(KernelEvents::RESPONSE, $events);
    }

    public function testOnKernelResponseIgnoresSubRequests(): void
    {
        $varnish = $this->createMock(VarnishCacheService::class);
        $varnish->expects($this->never())->method('purgeUrl');
        $cloudflare = $this->buildDisabledCloudflare();

        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = new Request();
        $response = new Response('', 200);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);

        $subscriber->onKernelResponse($event);
    }

    public function testOnKernelResponseIgnoresGetRequests(): void
    {
        $varnish = $this->createMock(VarnishCacheService::class);
        $varnish->expects($this->never())->method('purgeUrl');
        $varnish->expects($this->never())->method('banPattern');

        $cloudflare = $this->buildDisabledCloudflare();
        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/articles', 'GET');
        $request->attributes->set('_api_resource_class', Article::class);
        $response = new Response('', 200);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);
    }

    public function testOnKernelResponseIgnoresErrorResponses(): void
    {
        $varnish = $this->createMock(VarnishCacheService::class);
        $varnish->expects($this->never())->method('purgeUrl');

        $cloudflare = $this->buildDisabledCloudflare();
        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/articles/1', 'PUT');
        $request->attributes->set('_api_resource_class', Article::class);
        $response = new Response('', 400);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);
    }

    public function testOnKernelResponseIgnoresWhenNoResourceClass(): void
    {
        $varnish = $this->createMock(VarnishCacheService::class);
        $varnish->expects($this->never())->method('purgeUrl');
        $varnish->expects($this->never())->method('banPattern');

        $cloudflare = $this->buildDisabledCloudflare();
        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/articles/1', 'POST');
        $response = new Response('', 201);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);
    }

    public function testOnKernelResponseInvalidatesArticleOnPost(): void
    {
        $varnish = $this->createMock(VarnishCacheService::class);
        $varnish->expects($this->atLeastOnce())->method('banPattern');
        $cloudflare = $this->buildDisabledCloudflare();

        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/articles', 'POST');
        $request->attributes->set('_api_resource_class', Article::class);
        $response = new Response('', 201);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);
    }

    public function testOnKernelResponseInvalidatesArticleOnDelete(): void
    {
        $varnish = $this->createMock(VarnishCacheService::class);
        $varnish->expects($this->atLeastOnce())->method('purgeUrl');
        $cloudflare = $this->buildDisabledCloudflare();

        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/articles/42', 'DELETE');
        $request->attributes->set('_api_resource_class', Article::class);
        $request->attributes->set('id', '42');
        $response = new Response('', 204);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);
    }

    public function testOnKernelResponseInvalidatesArticleOnPatch(): void
    {
        $varnish = $this->createMock(VarnishCacheService::class);
        $varnish->expects($this->atLeastOnce())->method('purgeArticle')->with(10);
        $cloudflare = $this->buildDisabledCloudflare();

        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/articles/10', 'PATCH');
        $request->attributes->set('_api_resource_class', Article::class);
        $request->attributes->set('id', '10');
        $response = new Response('', 200);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);
    }

    public function testOnKernelResponseInvalidatesCategoryOnPost(): void
    {
        $varnish = $this->createMock(VarnishCacheService::class);
        $varnish->expects($this->atLeastOnce())->method('banPattern');
        $cloudflare = $this->buildDisabledCloudflare();

        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/categories', 'POST');
        $request->attributes->set('_api_resource_class', Category::class);
        $response = new Response('', 201);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);
    }

    public function testOnKernelResponseInvalidatesCategoryOnDelete(): void
    {
        $varnish = $this->createMock(VarnishCacheService::class);
        $varnish->expects($this->atLeastOnce())->method('purgeUrl');
        $cloudflare = $this->buildDisabledCloudflare();

        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/categories/3', 'DELETE');
        $request->attributes->set('_api_resource_class', Category::class);
        $request->attributes->set('id', '3');
        $response = new Response('', 204);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);
    }

    public function testOnKernelResponseInvalidatesCategoryOnPut(): void
    {
        $varnish = $this->createMock(VarnishCacheService::class);
        $varnish->expects($this->atLeastOnce())->method('purgeCategory')->with(5);
        $cloudflare = $this->buildDisabledCloudflare();

        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/categories/5', 'PUT');
        $request->attributes->set('_api_resource_class', Category::class);
        $request->attributes->set('id', '5');
        $response = new Response('', 200);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);
    }

    public function testOnKernelResponseIgnoresUnknownResourceClass(): void
    {
        $varnish = $this->createMock(VarnishCacheService::class);
        $varnish->expects($this->never())->method('purgeUrl');
        $varnish->expects($this->never())->method('banPattern');
        $cloudflare = $this->buildDisabledCloudflare();

        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/tags', 'POST');
        $request->attributes->set('_api_resource_class', 'App\\Entity\\Tag');
        $response = new Response('', 201);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);
    }

    public function testOnKernelResponseArticleDeleteWithCloudflareEnabled(): void
    {
        $varnish = $this->createMock(VarnishCacheService::class);
        $varnish->expects($this->atLeastOnce())->method('purgeUrl');
        $varnish->expects($this->atLeastOnce())->method('banPattern');

        $cloudflare = new CloudflareCacheService(
            $this->createStub(HttpClientInterface::class),
            new NullLogger(),
            'test-api-token',
            'test-zone-id',
            true // enabled
        );

        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare, 'https://api.test.md');

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/articles/42', 'DELETE');
        $request->attributes->set('_api_resource_class', Article::class);
        $request->attributes->set('id', '42');
        $response = new Response('', 204);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);
    }

    public function testOnKernelResponseArticlePostWithCloudflareEnabled(): void
    {
        $varnish = $this->createMock(VarnishCacheService::class);
        $varnish->expects($this->atLeastOnce())->method('banPattern');

        $cloudflare = new CloudflareCacheService(
            $this->createStub(HttpClientInterface::class),
            new NullLogger(),
            'test-api-token',
            'test-zone-id',
            true
        );

        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare, 'https://api.test.md');

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/articles', 'POST');
        $request->attributes->set('_api_resource_class', Article::class);
        $response = new Response('', 201);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);
    }

    public function testOnKernelResponseArticleUpdateWithCloudflareEnabled(): void
    {
        $varnish = $this->createMock(VarnishCacheService::class);
        $varnish->expects($this->atLeastOnce())->method('purgeArticle');
        $varnish->expects($this->atLeastOnce())->method('banPattern');

        $cloudflare = new CloudflareCacheService(
            $this->createStub(HttpClientInterface::class),
            new NullLogger(),
            'test-api-token',
            'test-zone-id',
            true
        );

        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare, 'https://api.test.md');

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/articles/10', 'PUT');
        $request->attributes->set('_api_resource_class', Article::class);
        $request->attributes->set('id', '10');
        $response = new Response('', 200);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);
    }

    public function testOnKernelResponseCategoryPostWithCloudflareEnabled(): void
    {
        $varnish = $this->createMock(VarnishCacheService::class);
        $varnish->expects($this->atLeastOnce())->method('banPattern');

        $cloudflare = new CloudflareCacheService(
            $this->createStub(HttpClientInterface::class),
            new NullLogger(),
            'test-api-token',
            'test-zone-id',
            true
        );

        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare, 'https://api.test.md');

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/categories', 'POST');
        $request->attributes->set('_api_resource_class', Category::class);
        $response = new Response('', 201);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);
    }

    public function testOnKernelResponseCategoryUpdateWithCloudflareEnabled(): void
    {
        $varnish = $this->createMock(VarnishCacheService::class);
        $varnish->expects($this->atLeastOnce())->method('purgeCategory');
        $varnish->expects($this->atLeastOnce())->method('banPattern');

        $cloudflare = new CloudflareCacheService(
            $this->createStub(HttpClientInterface::class),
            new NullLogger(),
            'test-api-token',
            'test-zone-id',
            true
        );

        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare, 'https://api.test.md');

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/categories/5', 'PATCH');
        $request->attributes->set('_api_resource_class', Category::class);
        $request->attributes->set('id', '5');
        $response = new Response('', 200);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);
    }

    public function testOnKernelResponseCategoryDeleteWithCloudflareEnabled(): void
    {
        $varnish = $this->createMock(VarnishCacheService::class);
        $varnish->expects($this->atLeastOnce())->method('purgeUrl');
        $varnish->expects($this->atLeastOnce())->method('banPattern');

        $cloudflare = new CloudflareCacheService(
            $this->createStub(HttpClientInterface::class),
            new NullLogger(),
            'test-api-token',
            'test-zone-id',
            true
        );

        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare, 'https://api.test.md');

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/categories/3', 'DELETE');
        $request->attributes->set('_api_resource_class', Category::class);
        $request->attributes->set('id', '3');
        $response = new Response('', 204);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);
    }

    public function testOnKernelResponseArticleDeleteWithoutIdPurgesCollections(): void
    {
        $varnish = $this->createMock(VarnishCacheService::class);
        $varnish->expects($this->never())->method('purgeUrl');
        $varnish->expects($this->atLeastOnce())->method('banPattern');
        $cloudflare = $this->buildDisabledCloudflare();

        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/articles', 'DELETE');
        $request->attributes->set('_api_resource_class', Article::class);
        // No 'id' attribute
        $response = new Response('', 204);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);
    }

    public function testOnKernelResponseArticleUpdateWithoutIdSkipsPurge(): void
    {
        $varnish = $this->createMock(VarnishCacheService::class);
        $varnish->expects($this->never())->method('purgeArticle');
        $varnish->expects($this->atLeastOnce())->method('banPattern');
        $cloudflare = $this->buildDisabledCloudflare();

        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/articles', 'PUT');
        $request->attributes->set('_api_resource_class', Article::class);
        $response = new Response('', 200);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);
    }

    public function testOnKernelResponseCategoryDeletePurgesArticlesPattern(): void
    {
        $varnish = $this->createMock(VarnishCacheService::class);
        // Should ban categories AND articles patterns on category delete
        $varnish->expects($this->atLeastOnce())->method('banPattern');
        $cloudflare = $this->buildDisabledCloudflare();

        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/categories/7', 'DELETE');
        $request->attributes->set('_api_resource_class', Category::class);
        $request->attributes->set('id', '7');
        $response = new Response('', 204);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);
    }
}
