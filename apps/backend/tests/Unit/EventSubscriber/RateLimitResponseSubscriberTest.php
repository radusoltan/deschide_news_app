<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\RateLimitResponseSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class RateLimitResponseSubscriberTest extends TestCase
{
    private RateLimitResponseSubscriber $subscriber;
    private HttpKernelInterface $kernel;

    protected function setUp(): void
    {
        $this->subscriber = new RateLimitResponseSubscriber();
        $this->kernel = $this->createStub(HttpKernelInterface::class);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = RateLimitResponseSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::RESPONSE, $events);
        $this->assertSame('onKernelResponse', $events[KernelEvents::RESPONSE]);
    }

    public function testAddsRateLimitHeadersToResponse(): void
    {
        $request = Request::create('/api/articles');
        $request->attributes->set('_rate_limit_headers', [
            'X-RateLimit-Remaining' => 99,
            'X-RateLimit-Limit' => 100,
        ]);

        $response = new Response('content', 200);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertSame('99', $response->headers->get('X-RateLimit-Remaining'));
        $this->assertSame('100', $response->headers->get('X-RateLimit-Limit'));
    }

    public function testSkipsNullHeaderValues(): void
    {
        $request = Request::create('/api/articles');
        $request->attributes->set('_rate_limit_headers', [
            'X-RateLimit-Remaining' => 50,
            'X-RateLimit-Retry-After' => null,
        ]);

        $response = new Response('content', 200);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertSame('50', $response->headers->get('X-RateLimit-Remaining'));
        $this->assertFalse($response->headers->has('X-RateLimit-Retry-After'));
    }

    public function testHandsNoRateLimitHeaders(): void
    {
        $request = Request::create('/api/articles');
        $response = new Response('content', 200);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        // Should not throw
        $this->subscriber->onKernelResponse($event);
        $this->assertFalse($response->headers->has('X-RateLimit-Remaining'));
    }
}
