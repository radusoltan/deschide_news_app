<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\HttpMetricsSubscriber;
use App\Service\MetricsService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class HttpMetricsSubscriberTest extends TestCase
{
    private MetricsService $metricsService;
    private HttpMetricsSubscriber $subscriber;
    private HttpKernelInterface $kernel;

    protected function setUp(): void
    {
        $this->metricsService = $this->createMock(MetricsService::class);
        $this->subscriber = new HttpMetricsSubscriber($this->metricsService);
        $this->kernel = $this->createStub(HttpKernelInterface::class);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = HttpMetricsSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
        $this->assertArrayHasKey(KernelEvents::RESPONSE, $events);
        $this->assertSame(['onKernelRequest', 1000], $events[KernelEvents::REQUEST]);
        $this->assertSame(['onKernelResponse', -1000], $events[KernelEvents::RESPONSE]);
    }

    public function testRecordsMetricsForRequestResponseCycle(): void
    {
        $request = Request::create('/api/articles');
        $response = new Response('content', 200);

        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $responseEvent = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->metricsService->expects($this->once())
            ->method('recordHttpRequestDuration')
            ->with(
                '/api/articles',
                $this->isType('float'),
                false
            );

        $this->subscriber->onKernelRequest($requestEvent);
        $this->subscriber->onKernelResponse($responseEvent);
    }

    public function testSkipsMetricsEndpoint(): void
    {
        $request = Request::create('/metrics');
        $response = new Response('', 200);

        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $responseEvent = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->metricsService->expects($this->never())
            ->method('recordHttpRequestDuration');

        $this->subscriber->onKernelRequest($requestEvent);
        $this->subscriber->onKernelResponse($responseEvent);
    }

    public function testSkipsSubRequests(): void
    {
        $request = Request::create('/api/articles');
        $response = new Response('content', 200);

        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST);
        $responseEvent = new ResponseEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);

        $this->metricsService->expects($this->never())
            ->method('recordHttpRequestDuration');

        $this->subscriber->onKernelRequest($requestEvent);
        $this->subscriber->onKernelResponse($responseEvent);
    }

    public function testSkipsResponseWithoutRequest(): void
    {
        // Create a response event for a request that was never tracked
        $request = Request::create('/api/articles');
        $response = new Response('content', 200);

        $responseEvent = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->metricsService->expects($this->never())
            ->method('recordHttpRequestDuration');

        // Only fire response without request
        $this->subscriber->onKernelResponse($responseEvent);
    }

    public function testNormalizesPathWithNumericIds(): void
    {
        $request = Request::create('/api/articles/123');
        $response = new Response('content', 200);

        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $responseEvent = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->metricsService->expects($this->once())
            ->method('recordHttpRequestDuration')
            ->with(
                '/api/articles/{id}',
                $this->isType('float'),
                false
            );

        $this->subscriber->onKernelRequest($requestEvent);
        $this->subscriber->onKernelResponse($responseEvent);
    }

    public function testDetectsCacheHit(): void
    {
        $request = Request::create('/api/articles');
        $response = new Response('content', 200);
        $response->headers->set('X-Cache-Hit', 'true');

        $requestEvent = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $responseEvent = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->metricsService->expects($this->once())
            ->method('recordHttpRequestDuration')
            ->with(
                '/api/articles',
                $this->isType('float'),
                true
            );

        $this->subscriber->onKernelRequest($requestEvent);
        $this->subscriber->onKernelResponse($responseEvent);
    }
}
