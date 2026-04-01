<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\CacheHeadersSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class CacheHeadersSubscriberTest extends TestCase
{
    private CacheHeadersSubscriber $subscriber;
    private HttpKernelInterface $kernel;

    protected function setUp(): void
    {
        $this->subscriber = new CacheHeadersSubscriber();
        $this->kernel = $this->createStub(HttpKernelInterface::class);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = CacheHeadersSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::RESPONSE, $events);
        // Check the priority is -256
        $this->assertSame(['onKernelResponse', -256], $events[KernelEvents::RESPONSE]);
    }

    public function testSetsCacheHeadersForPublicApiGet(): void
    {
        $request = Request::create('/api/articles', 'GET');
        $response = new Response('content', 200);

        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
        $this->assertSame('3600', $response->headers->getCacheControlDirective('max-age'));
        $this->assertSame('7200', $response->headers->getCacheControlDirective('s-maxage'));
    }

    public function testSetsVaryHeaders(): void
    {
        $request = Request::create('/api/articles', 'GET');
        $response = new Response('content', 200);

        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        $vary = $response->getVary();
        $this->assertContains('Content-Type', $vary);
        $this->assertContains('Accept-Language', $vary);
        $this->assertContains('Origin', $vary);
    }

    public function testSetsEtagHeader(): void
    {
        $request = Request::create('/api/articles', 'GET');
        $response = new Response('test-content', 200);

        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertTrue($response->headers->has('ETag'));
    }

    public function testSkipsNonMainRequest(): void
    {
        $request = Request::create('/api/articles', 'GET');
        $response = new Response('content', 200);

        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
    }

    public function testSkipsPostRequests(): void
    {
        $request = Request::create('/api/articles', 'POST');
        $response = new Response('content', 200);

        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
    }

    public function testSkipsNonOkResponses(): void
    {
        $request = Request::create('/api/articles', 'GET');
        $response = new Response('not found', 404);

        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
    }

    public function testSkipsNonApiPaths(): void
    {
        $request = Request::create('/profiler', 'GET');
        $response = new Response('content', 200);

        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertFalse($response->headers->hasCacheControlDirective('public'));
    }

    public function testSetsPrivateCacheForAuthenticatedRequests(): void
    {
        $request = Request::create('/api/articles', 'GET');
        $request->headers->set('Authorization', 'Bearer some-token');
        $response = new Response('content', 200);

        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-cache', $response->headers->get('Cache-Control'));
    }

    public function testAllowsHeadRequests(): void
    {
        $request = Request::create('/api/articles', 'HEAD');
        $response = new Response('', 200);

        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertTrue($response->headers->hasCacheControlDirective('public'));
    }
}
