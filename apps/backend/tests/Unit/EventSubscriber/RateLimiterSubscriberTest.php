<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\RateLimiterSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

class RateLimiterSubscriberTest extends TestCase
{
    private RateLimiterFactoryInterface $generalLimiter;
    private RateLimiterFactoryInterface $loginLimiter;
    private RateLimiterFactoryInterface $writeLimiter;
    private RateLimiterFactoryInterface $imageLimiter;
    private HttpKernelInterface $kernel;

    protected function setUp(): void
    {
        $this->generalLimiter = $this->createStub(RateLimiterFactoryInterface::class);
        $this->loginLimiter = $this->createStub(RateLimiterFactoryInterface::class);
        $this->writeLimiter = $this->createStub(RateLimiterFactoryInterface::class);
        $this->imageLimiter = $this->createStub(RateLimiterFactoryInterface::class);
        $this->kernel = $this->createStub(HttpKernelInterface::class);
    }

    private function createSubscriber(string $env = 'prod'): RateLimiterSubscriber
    {
        return new RateLimiterSubscriber(
            $this->generalLimiter,
            $this->loginLimiter,
            $this->writeLimiter,
            $this->imageLimiter,
            $env,
        );
    }

    public function testGetSubscribedEvents(): void
    {
        $events = RateLimiterSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
        $this->assertSame(['onKernelRequest', 10], $events[KernelEvents::REQUEST]);
    }

    public function testSkipsInTestEnvironment(): void
    {
        $subscriber = $this->createSubscriber('test');

        $request = Request::create('/api/articles', 'GET', [], [], [], ['REMOTE_ADDR' => '8.8.8.8']);
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // Should not interact with limiters at all
        $subscriber->onKernelRequest($event);
        $this->assertNull($event->getResponse());
    }

    public function testSkipsInDevEnvironment(): void
    {
        $subscriber = $this->createSubscriber('dev');

        $request = Request::create('/api/articles', 'GET', [], [], [], ['REMOTE_ADDR' => '8.8.8.8']);
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);
        $this->assertNull($event->getResponse());
    }

    public function testSkipsSubRequests(): void
    {
        $subscriber = $this->createSubscriber('prod');

        $request = Request::create('/api/articles', 'GET', [], [], [], ['REMOTE_ADDR' => '8.8.8.8']);
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST);

        $subscriber->onKernelRequest($event);
        $this->assertNull($event->getResponse());
    }

    public function testSkipsLocalhost(): void
    {
        $subscriber = $this->createSubscriber('prod');

        $request = Request::create('/api/articles', 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);
        $this->assertNull($event->getResponse());
    }

    public function testSkipsNonApiRoutes(): void
    {
        $subscriber = $this->createSubscriber('prod');

        $request = Request::create('/profiler', 'GET', [], [], [], ['REMOTE_ADDR' => '8.8.8.8']);
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);
        $this->assertNull($event->getResponse());
    }

    public function testAcceptedRequestSetsHeaders(): void
    {
        $subscriber = $this->createSubscriber('prod');

        $retryAfter = new \DateTimeImmutable('+60 seconds');
        $rateLimit = new RateLimit(99, $retryAfter, true, 100);

        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn($rateLimit);

        $this->generalLimiter->method('create')->willReturn($limiter);

        $request = Request::create('/api/articles', 'GET', [], [], [], ['REMOTE_ADDR' => '8.8.8.8']);
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        $this->assertNull($event->getResponse());
        $headers = $request->attributes->get('_rate_limit_headers');
        $this->assertSame(99, $headers['X-RateLimit-Remaining']);
        $this->assertSame(100, $headers['X-RateLimit-Limit']);
    }

    public function testRateLimitedRequestReturns429(): void
    {
        $subscriber = $this->createSubscriber('prod');

        $retryAfter = new \DateTimeImmutable('+60 seconds');
        $rateLimit = new RateLimit(0, $retryAfter, false, 100);

        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn($rateLimit);

        $this->generalLimiter->method('create')->willReturn($limiter);

        $request = Request::create('/api/articles', 'GET', [], [], [], ['REMOTE_ADDR' => '8.8.8.8']);
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        $response = $event->getResponse();
        $this->assertNotNull($response);
        $this->assertSame(429, $response->getStatusCode());
    }

    public function testUsesLoginLimiterForLoginEndpoint(): void
    {
        $subscriber = $this->createSubscriber('prod');

        $retryAfter = new \DateTimeImmutable('+60 seconds');
        $rateLimit = new RateLimit(4, $retryAfter, true, 5);

        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn($rateLimit);

        $this->loginLimiter->method('create')->willReturn($limiter);

        $request = Request::create('/api/login', 'POST', [], [], [], ['REMOTE_ADDR' => '8.8.8.8']);
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);
        $this->assertNull($event->getResponse());
    }

    public function testUsesWriteLimiterForPostRequests(): void
    {
        $subscriber = $this->createSubscriber('prod');

        $retryAfter = new \DateTimeImmutable('+60 seconds');
        $rateLimit = new RateLimit(49, $retryAfter, true, 50);

        $limiter = $this->createStub(LimiterInterface::class);
        $limiter->method('consume')->willReturn($rateLimit);

        $this->writeLimiter->method('create')->willReturn($limiter);

        $request = Request::create('/api/articles', 'POST', [], [], [], ['REMOTE_ADDR' => '8.8.8.8']);
        $event = new RequestEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);
        $this->assertNull($event->getResponse());
    }
}
