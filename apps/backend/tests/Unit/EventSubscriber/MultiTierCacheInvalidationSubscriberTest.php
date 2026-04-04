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
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponseInterface;

/**
 * Tests for MultiTierCacheInvalidationSubscriber.
 *
 * Because VarnishCacheService and CloudflareCacheService are final,
 * we create real instances backed by a spy HttpClient that records calls.
 */
class MultiTierCacheInvalidationSubscriberTest extends TestCase
{
    /** @var list<array{method: string, url: string}> */
    private array $httpCalls = [];

    /**
     * Build a spy HttpClient that records all request() calls.
     */
    private function buildSpyHttpClient(): HttpClientInterface
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

        return $client;
    }

    /**
     * Build a real VarnishCacheService with a spy HttpClient.
     */
    private function buildVarnish(bool $enabled = true): VarnishCacheService
    {
        return new VarnishCacheService(
            $this->buildSpyHttpClient(),
            new NullLogger(),
            '127.0.0.1',
            6081,
            $enabled
        );
    }

    /**
     * Build a real disabled CloudflareCacheService.
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

    /**
     * Build a real enabled CloudflareCacheService.
     */
    private function buildEnabledCloudflare(): CloudflareCacheService
    {
        $stubResponse = $this->createStub(HttpResponseInterface::class);
        $stubResponse->method('getStatusCode')->willReturn(200);
        $stubResponse->method('toArray')->willReturn(['success' => true]);

        $client = $this->createStub(HttpClientInterface::class);
        $client->method('request')->willReturn($stubResponse);

        return new CloudflareCacheService(
            $client,
            new NullLogger(),
            'test-api-token',
            'test-zone-id',
            true
        );
    }

    protected function setUp(): void
    {
        $this->httpCalls = [];
    }

    // ─── Helpers ──────────────────────────────────────────

    private function hasHttpCallMatching(string $httpMethod): bool
    {
        foreach ($this->httpCalls as $call) {
            if ($call['method'] === $httpMethod) {
                return true;
            }
        }

        return false;
    }

    private function countHttpCallsMatching(string $httpMethod): int
    {
        $count = 0;
        foreach ($this->httpCalls as $call) {
            if ($call['method'] === $httpMethod) {
                ++$count;
            }
        }

        return $count;
    }

    // ─── getSubscribedEvents ──────────────────────────────

    public function testGetSubscribedEventsReturnsKernelResponse(): void
    {
        $events = MultiTierCacheInvalidationSubscriber::getSubscribedEvents();
        $this->assertArrayHasKey(KernelEvents::RESPONSE, $events);
    }

    // ─── Skip scenarios ───────────────────────────────────

    public function testOnKernelResponseIgnoresSubRequests(): void
    {
        $varnish = $this->buildVarnish(true);
        $cloudflare = $this->buildDisabledCloudflare();
        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = new Request();
        $response = new Response('', 200);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST, $response);
        $subscriber->onKernelResponse($event);

        $this->assertEmpty($this->httpCalls, 'No HTTP calls should be made for sub-requests');
    }

    public function testOnKernelResponseIgnoresGetRequests(): void
    {
        $varnish = $this->buildVarnish(true);
        $cloudflare = $this->buildDisabledCloudflare();
        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/articles', 'GET');
        $request->attributes->set('_api_resource_class', Article::class);
        $response = new Response('', 200);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($event);

        $this->assertEmpty($this->httpCalls, 'No HTTP calls should be made for GET requests');
    }

    public function testOnKernelResponseIgnoresErrorResponses(): void
    {
        $varnish = $this->buildVarnish(true);
        $cloudflare = $this->buildDisabledCloudflare();
        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/articles/1', 'PUT');
        $request->attributes->set('_api_resource_class', Article::class);
        $response = new Response('', 400);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($event);

        $this->assertEmpty($this->httpCalls, 'No HTTP calls should be made for error responses');
    }

    public function testOnKernelResponseIgnoresWhenNoResourceClass(): void
    {
        $varnish = $this->buildVarnish(true);
        $cloudflare = $this->buildDisabledCloudflare();
        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/articles/1', 'POST');
        $response = new Response('', 201);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($event);

        $this->assertEmpty($this->httpCalls, 'No HTTP calls should be made when no resource class');
    }

    // ─── Article invalidation ─────────────────────────────

    public function testOnKernelResponseInvalidatesArticleOnPost(): void
    {
        $varnish = $this->buildVarnish(true);
        $cloudflare = $this->buildDisabledCloudflare();
        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/articles', 'POST');
        $request->attributes->set('_api_resource_class', Article::class);
        $response = new Response('', 201);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($event);

        // POST triggers banPattern calls (BAN HTTP method)
        $this->assertTrue(
            $this->hasHttpCallMatching('BAN'),
            'Article POST should trigger Varnish BAN calls'
        );
    }

    public function testOnKernelResponseInvalidatesArticleOnDelete(): void
    {
        $varnish = $this->buildVarnish(true);
        $cloudflare = $this->buildDisabledCloudflare();
        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/articles/42', 'DELETE');
        $request->attributes->set('_api_resource_class', Article::class);
        $request->attributes->set('id', '42');
        $response = new Response('', 204);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($event);

        // DELETE with ID triggers PURGE for specific URL
        $this->assertTrue(
            $this->hasHttpCallMatching('PURGE'),
            'Article DELETE should trigger Varnish PURGE calls'
        );
    }

    public function testOnKernelResponseInvalidatesArticleOnPatch(): void
    {
        $varnish = $this->buildVarnish(true);
        $cloudflare = $this->buildDisabledCloudflare();
        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/articles/10', 'PATCH');
        $request->attributes->set('_api_resource_class', Article::class);
        $request->attributes->set('id', '10');
        $response = new Response('', 200);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($event);

        // PATCH triggers purgeArticle which calls PURGE + BAN
        $this->assertTrue(
            $this->hasHttpCallMatching('PURGE'),
            'Article PATCH should trigger Varnish PURGE calls'
        );
    }

    // ─── Category invalidation ────────────────────────────

    public function testOnKernelResponseInvalidatesCategoryOnPost(): void
    {
        $varnish = $this->buildVarnish(true);
        $cloudflare = $this->buildDisabledCloudflare();
        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/categories', 'POST');
        $request->attributes->set('_api_resource_class', Category::class);
        $response = new Response('', 201);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($event);

        $this->assertTrue(
            $this->hasHttpCallMatching('BAN'),
            'Category POST should trigger Varnish BAN calls'
        );
    }

    public function testOnKernelResponseInvalidatesCategoryOnDelete(): void
    {
        $varnish = $this->buildVarnish(true);
        $cloudflare = $this->buildDisabledCloudflare();
        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/categories/3', 'DELETE');
        $request->attributes->set('_api_resource_class', Category::class);
        $request->attributes->set('id', '3');
        $response = new Response('', 204);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($event);

        $this->assertTrue(
            $this->hasHttpCallMatching('PURGE'),
            'Category DELETE should trigger Varnish PURGE calls'
        );
    }

    public function testOnKernelResponseInvalidatesCategoryOnPut(): void
    {
        $varnish = $this->buildVarnish(true);
        $cloudflare = $this->buildDisabledCloudflare();
        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/categories/5', 'PUT');
        $request->attributes->set('_api_resource_class', Category::class);
        $request->attributes->set('id', '5');
        $response = new Response('', 200);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($event);

        // PUT triggers purgeCategory which calls PURGE + BAN
        $this->assertTrue(
            $this->hasHttpCallMatching('PURGE'),
            'Category PUT should trigger Varnish PURGE calls'
        );
    }

    // ─── Unknown resource class ───────────────────────────

    public function testOnKernelResponseIgnoresUnknownResourceClass(): void
    {
        $varnish = $this->buildVarnish(true);
        $cloudflare = $this->buildDisabledCloudflare();
        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/tags', 'POST');
        $request->attributes->set('_api_resource_class', 'App\\Entity\\Tag');
        $response = new Response('', 201);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($event);

        $this->assertEmpty($this->httpCalls, 'No HTTP calls should be made for unknown resource classes');
    }

    // ─── Cloudflare enabled scenarios ─────────────────────

    public function testOnKernelResponseArticleDeleteWithCloudflareEnabled(): void
    {
        $varnish = $this->buildVarnish(true);
        $cloudflare = $this->buildEnabledCloudflare();
        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare, 'https://api.test.md');

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/articles/42', 'DELETE');
        $request->attributes->set('_api_resource_class', Article::class);
        $request->attributes->set('id', '42');
        $response = new Response('', 204);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($event);

        // Should trigger both Varnish and Cloudflare calls
        $this->assertTrue(
            $this->hasHttpCallMatching('PURGE') || $this->hasHttpCallMatching('BAN'),
            'Article DELETE should trigger Varnish cache invalidation'
        );
    }

    public function testOnKernelResponseArticlePostWithCloudflareEnabled(): void
    {
        $varnish = $this->buildVarnish(true);
        $cloudflare = $this->buildEnabledCloudflare();
        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare, 'https://api.test.md');

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/articles', 'POST');
        $request->attributes->set('_api_resource_class', Article::class);
        $response = new Response('', 201);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($event);

        $this->assertTrue(
            $this->hasHttpCallMatching('BAN'),
            'Article POST should trigger Varnish BAN calls'
        );
    }

    public function testOnKernelResponseArticleUpdateWithCloudflareEnabled(): void
    {
        $varnish = $this->buildVarnish(true);
        $cloudflare = $this->buildEnabledCloudflare();
        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare, 'https://api.test.md');

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/articles/10', 'PUT');
        $request->attributes->set('_api_resource_class', Article::class);
        $request->attributes->set('id', '10');
        $response = new Response('', 200);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($event);

        $this->assertTrue(
            $this->hasHttpCallMatching('PURGE'),
            'Article UPDATE should trigger Varnish PURGE calls'
        );
    }

    public function testOnKernelResponseCategoryPostWithCloudflareEnabled(): void
    {
        $varnish = $this->buildVarnish(true);
        $cloudflare = $this->buildEnabledCloudflare();
        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare, 'https://api.test.md');

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/categories', 'POST');
        $request->attributes->set('_api_resource_class', Category::class);
        $response = new Response('', 201);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($event);

        $this->assertTrue(
            $this->hasHttpCallMatching('BAN'),
            'Category POST should trigger Varnish BAN calls'
        );
    }

    public function testOnKernelResponseCategoryUpdateWithCloudflareEnabled(): void
    {
        $varnish = $this->buildVarnish(true);
        $cloudflare = $this->buildEnabledCloudflare();
        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare, 'https://api.test.md');

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/categories/5', 'PATCH');
        $request->attributes->set('_api_resource_class', Category::class);
        $request->attributes->set('id', '5');
        $response = new Response('', 200);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($event);

        $this->assertTrue(
            $this->hasHttpCallMatching('PURGE'),
            'Category UPDATE should trigger Varnish PURGE calls'
        );
    }

    public function testOnKernelResponseCategoryDeleteWithCloudflareEnabled(): void
    {
        $varnish = $this->buildVarnish(true);
        $cloudflare = $this->buildEnabledCloudflare();
        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare, 'https://api.test.md');

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/categories/3', 'DELETE');
        $request->attributes->set('_api_resource_class', Category::class);
        $request->attributes->set('id', '3');
        $response = new Response('', 204);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($event);

        $this->assertTrue(
            $this->hasHttpCallMatching('PURGE'),
            'Category DELETE should trigger Varnish PURGE calls'
        );
    }

    // ─── Edge cases ───────────────────────────────────────

    public function testOnKernelResponseArticleDeleteWithoutIdPurgesCollections(): void
    {
        $varnish = $this->buildVarnish(true);
        $cloudflare = $this->buildDisabledCloudflare();
        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/articles', 'DELETE');
        $request->attributes->set('_api_resource_class', Article::class);
        // No 'id' attribute
        $response = new Response('', 204);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($event);

        // Without ID, should still ban patterns but not purge specific URL
        $this->assertTrue(
            $this->hasHttpCallMatching('BAN'),
            'Article DELETE without ID should still trigger BAN calls'
        );
        // No PURGE call for specific article URL (no ID)
        $purgeCallsForArticle = array_filter($this->httpCalls, fn ($c) => $c['method'] === 'PURGE' && str_contains($c['url'], '/api/articles/'));
        $this->assertEmpty($purgeCallsForArticle, 'Should not PURGE a specific article URL without ID');
    }

    public function testOnKernelResponseArticleUpdateWithoutIdSkipsPurge(): void
    {
        $varnish = $this->buildVarnish(true);
        $cloudflare = $this->buildDisabledCloudflare();
        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/articles', 'PUT');
        $request->attributes->set('_api_resource_class', Article::class);
        $response = new Response('', 200);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($event);

        // Update without ID should still trigger BAN patterns
        $this->assertTrue(
            $this->hasHttpCallMatching('BAN'),
            'Article UPDATE without ID should trigger BAN patterns'
        );
    }

    public function testOnKernelResponseCategoryDeletePurgesArticlesPattern(): void
    {
        $varnish = $this->buildVarnish(true);
        $cloudflare = $this->buildDisabledCloudflare();
        $subscriber = new MultiTierCacheInvalidationSubscriber($varnish, $cloudflare);

        $kernel = $this->createStub(HttpKernelInterface::class);
        $request = Request::create('/api/categories/7', 'DELETE');
        $request->attributes->set('_api_resource_class', Category::class);
        $request->attributes->set('id', '7');
        $response = new Response('', 204);

        $event = new ResponseEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $response);
        $subscriber->onKernelResponse($event);

        // Category DELETE should ban both categories AND articles patterns
        $banCalls = array_filter($this->httpCalls, fn ($c) => $c['method'] === 'BAN');
        $this->assertNotEmpty($banCalls, 'Category DELETE should trigger BAN calls for collections');
    }
}
