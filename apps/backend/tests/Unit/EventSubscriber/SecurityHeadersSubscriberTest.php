<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\EventSubscriber\SecurityHeadersSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class SecurityHeadersSubscriberTest extends TestCase
{
    private HttpKernelInterface $kernel;

    protected function setUp(): void
    {
        $this->kernel = $this->createStub(HttpKernelInterface::class);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = SecurityHeadersSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::RESPONSE, $events);
        $this->assertSame('onKernelResponse', $events[KernelEvents::RESPONSE]);
    }

    public function testSetsSecurityHeaders(): void
    {
        $subscriber = new SecurityHeadersSubscriber('dev');

        $request = Request::create('/api/articles');
        $response = new Response('content', 200);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        $this->assertTrue($response->headers->has('Content-Security-Policy'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertSame('DENY', $response->headers->get('X-Frame-Options'));
        $this->assertSame('1; mode=block', $response->headers->get('X-XSS-Protection'));
        $this->assertSame('strict-origin-when-cross-origin', $response->headers->get('Referrer-Policy'));
        $this->assertStringContainsString('geolocation=()', $response->headers->get('Permissions-Policy'));
    }

    public function testSetsHstsInProduction(): void
    {
        $subscriber = new SecurityHeadersSubscriber('prod');

        $request = Request::create('/api/articles');
        $response = new Response('content', 200);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        $this->assertTrue($response->headers->has('Strict-Transport-Security'));
        $hsts = $response->headers->get('Strict-Transport-Security');
        $this->assertStringContainsString('max-age=31536000', $hsts);
        $this->assertStringContainsString('includeSubDomains', $hsts);
    }

    public function testDoesNotSetHstsInDev(): void
    {
        $subscriber = new SecurityHeadersSubscriber('dev');

        $request = Request::create('/api/articles');
        $response = new Response('content', 200);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        $this->assertFalse($response->headers->has('Strict-Transport-Security'));
    }

    public function testSkipsSubRequests(): void
    {
        $subscriber = new SecurityHeadersSubscriber('dev');

        $request = Request::create('/api/articles');
        $response = new Response('content', 200);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        $this->assertFalse($response->headers->has('X-Content-Type-Options'));
    }

    public function testCspContainsExpectedDirectives(): void
    {
        $subscriber = new SecurityHeadersSubscriber('dev');

        $request = Request::create('/api/articles');
        $response = new Response('content', 200);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $subscriber->onKernelResponse($event);

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("script-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }
}
