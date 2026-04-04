<?php

declare(strict_types=1);

namespace App\Tests\Unit\EventSubscriber;

use App\Entity\Article;
use App\Entity\Category;
use App\EventSubscriber\VarnishCacheInvalidationSubscriber;
use App\Service\VarnishCacheService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponseInterface;

/**
 * Tests for VarnishCacheInvalidationSubscriber.
 *
 * Because VarnishCacheService is final, we create real instances
 * backed by a spy HttpClient that records all HTTP calls.
 */
class VarnishCacheInvalidationSubscriberTest extends TestCase
{
    /** @var list<array{method: string, url: string}> */
    private array $httpCalls = [];
    private VarnishCacheService $varnishCache;
    private VarnishCacheInvalidationSubscriber $subscriber;
    private HttpKernelInterface $kernel;

    protected function setUp(): void
    {
        $this->httpCalls = [];
        $this->varnishCache = $this->buildVarnish(true);
        $this->subscriber = new VarnishCacheInvalidationSubscriber($this->varnishCache);
        $this->kernel = $this->createStub(HttpKernelInterface::class);
    }

    private function buildVarnish(bool $enabled): VarnishCacheService
    {
        $calls = &$this->httpCalls;
        $stubResponse = $this->createStub(HttpResponseInterface::class);
        $stubResponse->method('getStatusCode')->willReturn(200);

        $client = $this->createStub(HttpClientInterface::class);
        $client->method('request')->willReturnCallback(
            function (string $method, string $url) use (&$calls, $stubResponse): HttpResponseInterface {
                $calls[] = ['method' => $method, 'url' => $url];

                return $stubResponse;
            }
        );

        return new VarnishCacheService($client, new NullLogger(), '127.0.0.1', 6081, $enabled);
    }

    private function hasHttpCallMatching(string $httpMethod): bool
    {
        foreach ($this->httpCalls as $call) {
            if ($call['method'] === $httpMethod) {
                return true;
            }
        }

        return false;
    }

    private function hasHttpCallContaining(string $httpMethod, string $urlPart): bool
    {
        foreach ($this->httpCalls as $call) {
            if ($call['method'] === $httpMethod && str_contains($call['url'], $urlPart)) {
                return true;
            }
        }

        return false;
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

        $this->subscriber->onKernelResponse($event);

        $this->assertTrue(
            $this->hasHttpCallContaining('PURGE', '/api/articles/42'),
            'Article DELETE should PURGE the specific article URL'
        );
        $this->assertTrue(
            $this->hasHttpCallMatching('BAN'),
            'Article DELETE should BAN collection patterns'
        );
    }

    public function testInvalidatesArticleCacheOnPost(): void
    {
        $request = Request::create('/api/articles', 'POST');
        $request->attributes->set('_api_resource_class', Article::class);

        $response = new Response('', 201);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        // POST should not PURGE a specific article (no existing cached page)
        $purgeArticleCalls = array_filter(
            $this->httpCalls,
            fn ($c) => $c['method'] === 'PURGE' && str_contains($c['url'], '/api/articles/')
        );
        $this->assertEmpty($purgeArticleCalls, 'Article POST should not PURGE a specific article URL');
        $this->assertTrue(
            $this->hasHttpCallMatching('BAN'),
            'Article POST should BAN collection patterns'
        );
    }

    public function testInvalidatesArticleCacheOnUpdate(): void
    {
        $request = Request::create('/api/articles/10', 'PUT');
        $request->attributes->set('_api_resource_class', Article::class);
        $request->attributes->set('id', '10');

        $response = new Response('', 200);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        // purgeArticle calls purgeUrl + banPattern internally
        $this->assertTrue(
            $this->hasHttpCallContaining('PURGE', '/api/articles/10'),
            'Article UPDATE should PURGE the specific article'
        );
    }

    public function testInvalidatesCategoryCacheOnDelete(): void
    {
        $request = Request::create('/api/categories/5', 'DELETE');
        $request->attributes->set('_api_resource_class', Category::class);
        $request->attributes->set('id', '5');

        $response = new Response('', 200);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertTrue(
            $this->hasHttpCallContaining('PURGE', '/api/categories/5'),
            'Category DELETE should PURGE the specific category URL'
        );
        $this->assertTrue(
            $this->hasHttpCallMatching('BAN'),
            'Category DELETE should BAN collection patterns'
        );
    }

    public function testInvalidatesCategoryCacheOnPost(): void
    {
        $request = Request::create('/api/categories', 'POST');
        $request->attributes->set('_api_resource_class', Category::class);

        $response = new Response('', 201);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertTrue(
            $this->hasHttpCallMatching('BAN'),
            'Category POST should BAN collection patterns'
        );
    }

    public function testInvalidatesCategoryCacheOnUpdate(): void
    {
        $request = Request::create('/api/categories/3', 'PATCH');
        $request->attributes->set('_api_resource_class', Category::class);
        $request->attributes->set('id', '3');

        $response = new Response('', 200);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        // purgeCategory calls purgeUrl + banPattern internally
        $this->assertTrue(
            $this->hasHttpCallContaining('PURGE', '/api/categories/3'),
            'Category UPDATE should PURGE the specific category'
        );
    }

    public function testSkipsGetRequests(): void
    {
        $request = Request::create('/api/articles', 'GET');
        $request->attributes->set('_api_resource_class', Article::class);

        $response = new Response('', 200);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertEmpty($this->httpCalls, 'GET requests should not trigger any cache invalidation');
    }

    public function testSkipsSubRequests(): void
    {
        $request = Request::create('/api/articles', 'DELETE');
        $request->attributes->set('_api_resource_class', Article::class);

        $response = new Response('', 200);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertEmpty($this->httpCalls, 'Sub-requests should not trigger cache invalidation');
    }

    public function testSkipsErrorResponses(): void
    {
        $request = Request::create('/api/articles', 'POST');
        $request->attributes->set('_api_resource_class', Article::class);

        $response = new Response('', 500);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertEmpty($this->httpCalls, 'Error responses should not trigger cache invalidation');
    }

    public function testSkipsNonApiResourceRequests(): void
    {
        $request = Request::create('/api/articles', 'POST');
        // No _api_resource_class attribute

        $response = new Response('', 200);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertEmpty($this->httpCalls, 'Requests without resource class should not trigger cache invalidation');
    }

    public function testHandlesArticleUpdateWithoutId(): void
    {
        $request = Request::create('/api/articles', 'PUT');
        $request->attributes->set('_api_resource_class', Article::class);
        // No 'id' attribute

        $response = new Response('', 200);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        // Fallback: should ban all articles pattern
        $this->assertTrue(
            $this->hasHttpCallMatching('BAN'),
            'Article UPDATE without ID should trigger BAN fallback'
        );
    }

    public function testIgnoresUnknownResourceClass(): void
    {
        $request = Request::create('/api/images', 'POST');
        $request->attributes->set('_api_resource_class', 'App\Entity\Image');

        $response = new Response('', 201);
        $event = new ResponseEvent($this->kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);

        $this->subscriber->onKernelResponse($event);

        $this->assertEmpty($this->httpCalls, 'Unknown resource classes should not trigger cache invalidation');
    }
}
