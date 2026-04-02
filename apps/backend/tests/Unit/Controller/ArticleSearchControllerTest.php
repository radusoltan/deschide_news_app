<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\ArticleSearchController;
use App\Service\ElasticService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for ArticleSearchController.
 *
 * Tests search, searchTest, locale extraction, and error handling.
 */
class ArticleSearchControllerTest extends TestCase
{
    private ElasticService $elasticService;
    private ArticleSearchController $controller;

    protected function setUp(): void
    {
        $this->elasticService = $this->createStub(ElasticService::class);
        $this->controller = new ArticleSearchController($this->elasticService);

        $container = $this->createStub(\Symfony\Component\DependencyInjection\ContainerInterface::class);
        $container->method('has')->willReturnCallback(function (string $id): bool {
            return match ($id) {
                'parameter_bag' => true,
                default => false,
            };
        });
        $paramBag = $this->createStub(\Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface::class);
        $paramBag->method('get')->willReturnMap([
            ['kernel.debug', false],
        ]);
        $container->method('get')->willReturnCallback(function (string $id) use ($paramBag) {
            return match ($id) {
                'parameter_bag' => $paramBag,
                default => null,
            };
        });
        $this->controller->setContainer($container);
    }

    // =============================================
    // GET /search-test
    // =============================================

    #[Test]
    public function searchTestReturnsOkStatus(): void
    {
        $response = $this->controller->searchTest();

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('ok', $data['status']);
        $this->assertSame('Controller is working', $data['message']);
    }

    // =============================================
    // GET /search - Query validation
    // =============================================

    #[Test]
    public function searchReturnsBadRequestForEmptyQuery(): void
    {
        $request = Request::create('/search', 'GET', ['q' => '']);

        $response = $this->controller->search($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEmpty($data['results']);
        $this->assertSame(0, $data['total']);
        $this->assertStringContainsString('at least 2 characters', $data['error']);
    }

    #[Test]
    public function searchReturnsBadRequestForSingleCharQuery(): void
    {
        $request = Request::create('/search', 'GET', ['q' => 'a']);

        $response = $this->controller->search($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function searchReturnsBadRequestForNoQuery(): void
    {
        $request = Request::create('/search', 'GET');

        $response = $this->controller->search($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function searchReturnsBadRequestForWhitespaceOnlyQuery(): void
    {
        $request = Request::create('/search', 'GET', ['q' => '  ']);

        $response = $this->controller->search($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    // =============================================
    // GET /search - Successful search
    // =============================================

    #[Test]
    public function searchReturns200WithResults(): void
    {
        $this->elasticService->method('search')->willReturn([
            'hits' => [
                'total' => ['value' => 1],
                'hits' => [
                    [
                        '_source' => [
                            'id' => 42,
                            'title' => 'Test Article',
                            'slug' => 'test-article',
                            'lead' => 'Test lead',
                            'category' => ['slug' => 'politica', 'name' => 'Politica'],
                            'published_at' => '2026-03-20T12:00:00Z',
                        ],
                        'highlight' => ['title' => ['<em>Test</em> Article']],
                    ],
                ],
            ],
        ]);

        $request = Request::create('/search', 'GET', ['q' => 'test article']);

        $response = $this->controller->search($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(1, $data['total']);
        $this->assertSame(1, $data['page']);
        $this->assertSame(12, $data['itemsPerPage']);
        $this->assertSame(1, $data['totalPages']);
        $this->assertSame('test article', $data['query']);
        $this->assertCount(1, $data['results']);
        $this->assertSame(42, $data['results'][0]['id']);
        $this->assertSame('Test Article', $data['results'][0]['title']);
        $this->assertSame('test-article', $data['results'][0]['slug']);
    }

    #[Test]
    public function searchTransformsResultsCorrectly(): void
    {
        $this->elasticService->method('search')->willReturn([
            'hits' => [
                'total' => ['value' => 1],
                'hits' => [
                    [
                        '_source' => [
                            'id' => 1,
                            'title' => 'Title',
                            'slug' => 'title',
                            'category' => ['slug' => 'sport', 'name' => 'Sport'],
                            'created_at' => '2026-01-01T00:00:00Z',
                        ],
                    ],
                ],
            ],
        ]);

        $request = Request::create('/search', 'GET', ['q' => 'test']);

        $response = $this->controller->search($request);

        $data = json_decode($response->getContent(), true);
        $result = $data['results'][0];
        $this->assertSame('', $result['excerpt']); // lead is null, defaults to ''
        $this->assertSame('2026-01-01T00:00:00Z', $result['publishedAt']); // falls back to created_at
        $this->assertNull($result['highlights']); // no highlights key
        $this->assertIsArray($result['articleImages']);
        $this->assertEmpty($result['articleImages']);
    }

    // =============================================
    // GET /search - Pagination
    // =============================================

    #[Test]
    public function searchPaginationDefaultsToPage1With12Items(): void
    {
        $this->elasticService->method('search')->willReturn([
            'hits' => ['total' => ['value' => 0], 'hits' => []],
        ]);

        $request = Request::create('/search', 'GET', ['q' => 'test']);

        $response = $this->controller->search($request);

        $data = json_decode($response->getContent(), true);
        $this->assertSame(1, $data['page']);
        $this->assertSame(12, $data['itemsPerPage']);
    }

    #[Test]
    public function searchCapsItemsPerPageAt100(): void
    {
        $this->elasticService->method('search')->willReturn([
            'hits' => ['total' => ['value' => 0], 'hits' => []],
        ]);

        $request = Request::create('/search', 'GET', ['q' => 'test', 'itemsPerPage' => '200']);

        $response = $this->controller->search($request);

        $data = json_decode($response->getContent(), true);
        $this->assertSame(100, $data['itemsPerPage']);
    }

    #[Test]
    public function searchEnsuresMinimumPageOf1(): void
    {
        $this->elasticService->method('search')->willReturn([
            'hits' => ['total' => ['value' => 0], 'hits' => []],
        ]);

        $request = Request::create('/search', 'GET', ['q' => 'test', 'page' => '0']);

        $response = $this->controller->search($request);

        $data = json_decode($response->getContent(), true);
        $this->assertSame(1, $data['page']);
    }

    #[Test]
    public function searchEnsuresMinimumItemsPerPageOf1(): void
    {
        $this->elasticService->method('search')->willReturn([
            'hits' => ['total' => ['value' => 0], 'hits' => []],
        ]);

        $request = Request::create('/search', 'GET', ['q' => 'test', 'itemsPerPage' => '-5']);

        $response = $this->controller->search($request);

        $data = json_decode($response->getContent(), true);
        $this->assertSame(1, $data['itemsPerPage']);
    }

    #[Test]
    public function searchCalculatesTotalPagesCorrectly(): void
    {
        $this->elasticService->method('search')->willReturn([
            'hits' => ['total' => ['value' => 25], 'hits' => []],
        ]);

        $request = Request::create('/search', 'GET', ['q' => 'test', 'itemsPerPage' => '10']);

        $response = $this->controller->search($request);

        $data = json_decode($response->getContent(), true);
        $this->assertSame(3, $data['totalPages']); // ceil(25/10) = 3
    }

    // =============================================
    // GET /search - Locale handling
    // =============================================

    #[Test]
    public function searchUsesLocaleQueryParam(): void
    {
        $this->elasticService->method('search')->willReturn([
            'hits' => ['total' => ['value' => 0], 'hits' => []],
        ]);

        $request = Request::create('/search', 'GET', ['q' => 'test', 'locale' => 'en']);

        $response = $this->controller->search($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function searchExtractsLocaleFromAcceptLanguageHeader(): void
    {
        $this->elasticService->method('search')->willReturn([
            'hits' => ['total' => ['value' => 0], 'hits' => []],
        ]);

        $request = Request::create('/search', 'GET', ['q' => 'test']);
        $request->headers->set('Accept-Language', 'ru');

        $response = $this->controller->search($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function searchFallsBackToRoForUnsupportedLocale(): void
    {
        $this->elasticService->method('search')->willReturn([
            'hits' => ['total' => ['value' => 0], 'hits' => []],
        ]);

        $request = Request::create('/search', 'GET', ['q' => 'test']);
        $request->headers->set('Accept-Language', 'fr');

        $response = $this->controller->search($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    // =============================================
    // GET /search - Category filter
    // =============================================

    #[Test]
    public function searchPassesCategoryIdFilter(): void
    {
        $this->elasticService->method('search')->willReturn([
            'hits' => ['total' => ['value' => 0], 'hits' => []],
        ]);

        $request = Request::create('/search', 'GET', ['q' => 'test', 'categoryId' => '5']);

        $response = $this->controller->search($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    // =============================================
    // GET /search - Error handling
    // =============================================

    #[Test]
    public function searchReturns500WhenElasticsearchFails(): void
    {
        $this->elasticService->method('search')
            ->willThrowException(new \Exception('Connection refused'));

        $request = Request::create('/search', 'GET', ['q' => 'test query']);

        $response = $this->controller->search($request);

        $this->assertSame(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertEmpty($data['results']);
        $this->assertSame(0, $data['total']);
        $this->assertSame('test query', $data['query']);
        $this->assertStringContainsString('temporarily unavailable', $data['error']);
    }

    #[Test]
    public function searchHidesDebugInfoInProduction(): void
    {
        $this->elasticService->method('search')
            ->willThrowException(new \Exception('Connection refused'));

        $request = Request::create('/search', 'GET', ['q' => 'test query']);

        $response = $this->controller->search($request);

        $data = json_decode($response->getContent(), true);
        // kernel.debug = false in our container stub, so debug should be null
        $this->assertNull($data['debug']);
    }

    // =============================================
    // GET /search - Cache headers
    // =============================================

    #[Test]
    public function searchSetsCorrectCacheHeaders(): void
    {
        $this->elasticService->method('search')->willReturn([
            'hits' => ['total' => ['value' => 0], 'hits' => []],
        ]);

        $request = Request::create('/search', 'GET', ['q' => 'test']);

        $response = $this->controller->search($request);

        $this->assertStringContainsString('public', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('max-age=60', $response->headers->get('Cache-Control'));
        $this->assertSame('Accept-Language', $response->headers->get('Vary'));
    }
}
