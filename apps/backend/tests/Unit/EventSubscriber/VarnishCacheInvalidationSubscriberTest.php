<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\Article;
use App\Entity\Category;
use App\EventSubscriber\VarnishCacheInvalidationSubscriber;
use App\Service\VarnishCacheService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class VarnishCacheInvalidationSubscriberTest extends TestCase
{
    private VarnishCacheService $varnishCache;
    private VarnishCacheInvalidationSubscriber $subscriber;
    private HttpKernelInterface $kernel;

    protected function setUp(): void
    {
        $this->varnishCache = $this->createMock(VarnishCacheService::class);
        $this->subscriber = new VarnishCacheInvalidationSubscriber($this->varnishCache);
        $this->kernel = $this->createStub(HttpKernelInterface::class);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = VarnishCacheInvalidationSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::RESPONSE, $events);
        $this->assertSame(['onKernelResponse', -10], $events[KernelEvents::RESPONSE]);
    }

    public function testInvalidatesArticleCacheOnDelete(): void
    {
        $request = Request::create('/api/articles/42', 'DELETE');
        $request->attributes->set('_api_resource_class', Article::class);
        $request->attributes->set('id', '42');

        $response = new Response('', 200);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->varnishCache->expects($this->once())
            ->method('purgeUrl')
            ->with('/api/articles/42');

        $this->varnishCache->expects($this->atLeastOnce())
            ->method('banPattern');

        $this->subscriber->onKernelResponse($event);
    }

    public function testInvalidatesArticleCacheOnPost(): void
    {
        $request = Request::create('/api/articles', 'POST');
        $request->attributes->set('_api_resource_class', Article::class);

        $response = new Response('', 201);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->varnishCache->expects($this->never())
            ->method('purgeUrl');

        $this->varnishCache->expects($this->atLeastOnce())
            ->method('banPattern');

        $this->subscriber->onKernelResponse($event);
    }

    public function testInvalidatesArticleCacheOnUpdate(): void
    {
        $request = Request::create('/api/articles/10', 'PUT');
        $request->attributes->set('_api_resource_class', Article::class);
        $request->attributes->set('id', '10');

        $response = new Response('', 200);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->varnishCache->expects($this->once())
            ->method('purgeArticle')
            ->with(10);

        $this->subscriber->onKernelResponse($event);
    }

    public function testInvalidatesCategoryCacheOnDelete(): void
    {
        $request = Request::create('/api/categories/5', 'DELETE');
        $request->attributes->set('_api_resource_class', Category::class);
        $request->attributes->set('id', '5');

        $response = new Response('', 200);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->varnishCache->expects($this->once())
            ->method('purgeUrl')
            ->with('/api/categories/5');

        $this->varnishCache->expects($this->atLeastOnce())
            ->method('banPattern');

        $this->subscriber->onKernelResponse($event);
    }

    public function testInvalidatesCategoryCacheOnPost(): void
    {
        $request = Request::create('/api/categories', 'POST');
        $request->attributes->set('_api_resource_class', Category::class);

        $response = new Response('', 201);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->varnishCache->expects($this->atLeastOnce())
            ->method('banPattern');

        $this->subscriber->onKernelResponse($event);
    }

    public function testInvalidatesCategoryCacheOnUpdate(): void
    {
        $request = Request::create('/api/categories/3', 'PATCH');
        $request->attributes->set('_api_resource_class', Category::class);
        $request->attributes->set('id', '3');

        $response = new Response('', 200);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->varnishCache->expects($this->once())
            ->method('purgeCategory')
            ->with(3);

        $this->subscriber->onKernelResponse($event);
    }

    public function testSkipsGetRequests(): void
    {
        $request = Request::create('/api/articles', 'GET');
        $request->attributes->set('_api_resource_class', Article::class);

        $response = new Response('', 200);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->varnishCache->expects($this->never())
            ->method('purgeUrl');
        $this->varnishCache->expects($this->never())
            ->method('banPattern');

        $this->subscriber->onKernelResponse($event);
    }

    public function testSkipsSubRequests(): void
    {
        $request = Request::create('/api/articles', 'DELETE');
        $request->attributes->set('_api_resource_class', Article::class);

        $response = new Response('', 200);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);

        $this->varnishCache->expects($this->never())
            ->method('purgeUrl');

        $this->subscriber->onKernelResponse($event);
    }

    public function testSkipsErrorResponses(): void
    {
        $request = Request::create('/api/articles', 'POST');
        $request->attributes->set('_api_resource_class', Article::class);

        $response = new Response('', 500);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->varnishCache->expects($this->never())
            ->method('purgeUrl');
        $this->varnishCache->expects($this->never())
            ->method('banPattern');

        $this->subscriber->onKernelResponse($event);
    }

    public function testSkipsNonApiResourceRequests(): void
    {
        $request = Request::create('/api/articles', 'POST');
        // No _api_resource_class attribute

        $response = new Response('', 200);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->varnishCache->expects($this->never())
            ->method('purgeUrl');

        $this->subscriber->onKernelResponse($event);
    }

    public function testHandlesArticleUpdateWithoutId(): void
    {
        $request = Request::create('/api/articles', 'PUT');
        $request->attributes->set('_api_resource_class', Article::class);
        // No 'id' attribute

        $response = new Response('', 200);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->varnishCache->expects($this->once())
            ->method('banPattern')
            ->with('/api/articles.*');

        $this->subscriber->onKernelResponse($event);
    }

    public function testIgnoresUnknownResourceClass(): void
    {
        $request = Request::create('/api/images', 'POST');
        $request->attributes->set('_api_resource_class', 'App\Entity\Image');

        $response = new Response('', 201);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->varnishCache->expects($this->never())
            ->method('purgeUrl');
        $this->varnishCache->expects($this->never())
            ->method('banPattern');

        $this->subscriber->onKernelResponse($event);
    }
}
