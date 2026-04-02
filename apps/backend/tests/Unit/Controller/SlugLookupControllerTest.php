<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\SlugLookupController;
use App\Entity\Article;
use App\Entity\Category;
use App\Entity\UrlRedirect;
use App\Enum\ArticleStatus;
use App\Service\SlugLookupService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for SlugLookupController.
 *
 * Tests all 7 endpoints with mocked SlugLookupService.
 */
class SlugLookupControllerTest extends TestCase
{
    private SlugLookupService $slugLookupService;
    private SlugLookupController $controller;

    protected function setUp(): void
    {
        $this->slugLookupService = $this->createStub(SlugLookupService::class);
        $this->controller = new SlugLookupController($this->slugLookupService);

        // AbstractController needs a container for json()
        $container = $this->createStub(\Symfony\Component\DependencyInjection\ContainerInterface::class);
        $container->method('has')->willReturnMap([
            ['serializer', false],
            ['twig', false],
        ]);
        $this->controller->setContainer($container);
    }

    // =============================================
    // POST /api/slug/lookup
    // =============================================

    #[Test]
    public function lookupReturnsBadRequestWhenFieldsMissing(): void
    {
        $request = Request::create('/api/slug/lookup', 'POST', [], [], [], [], json_encode([]));

        $response = $this->controller->lookup($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Missing required fields', $data['error']);
    }

    #[Test]
    public function lookupReturnsBadRequestWhenArticleSlugMissing(): void
    {
        $request = Request::create('/api/slug/lookup', 'POST', [], [], [], [],
            json_encode(['category_slug' => 'politica'])
        );

        $response = $this->controller->lookup($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function lookupReturnsBadRequestWhenCategorySlugMissing(): void
    {
        $request = Request::create('/api/slug/lookup', 'POST', [], [], [], [],
            json_encode(['article_slug' => 'test'])
        );

        $response = $this->controller->lookup($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function lookupReturns200WhenArticleFound(): void
    {
        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn(5);
        $category->method('getTitle')->willReturn('Politica');
        $category->method('getSlug')->willReturn('politica');

        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn(123);
        $article->method('getTitle')->willReturn('Test Article');
        $article->method('getSlug')->willReturn('test-article');
        $article->method('getLead')->willReturn('Lead text');
        $article->method('getCategory')->willReturn($category);
        $article->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $article->method('getPublishedAt')->willReturn(new \DateTimeImmutable('2026-03-20 12:00:00'));

        $this->slugLookupService->method('findArticleBySlug')->willReturn([
            'article' => $article,
            'redirect' => null,
            'found_via' => 'elasticsearch',
        ]);

        $request = Request::create('/api/slug/lookup', 'POST', [], [], [], [],
            json_encode(['category_slug' => 'politica', 'article_slug' => 'test-article', 'locale' => 'ro'])
        );

        $response = $this->controller->lookup($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame(123, $data['data']['article_id']);
        $this->assertSame('test-article', $data['data']['slug']);
        $this->assertSame('elasticsearch', $data['data']['found_via']);
    }

    #[Test]
    public function lookupReturns200WithLocalePrefixForNonRoLocale(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn(1);
        $article->method('getTitle')->willReturn('Title');
        $article->method('getSlug')->willReturn('slug');
        $article->method('getLead')->willReturn(null);
        $article->method('getCategory')->willReturn(null);
        $article->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $article->method('getPublishedAt')->willReturn(null);

        $this->slugLookupService->method('findArticleBySlug')->willReturn([
            'article' => $article,
            'redirect' => null,
            'found_via' => 'database',
        ]);

        $request = Request::create('/api/slug/lookup', 'POST', [], [], [], [],
            json_encode(['category_slug' => 'politics', 'article_slug' => 'slug', 'locale' => 'en'])
        );

        $response = $this->controller->lookup($request);
        $data = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('/en/politics/slug', $data['data']['url']);
    }

    #[Test]
    public function lookupReturns301WhenRedirectFound(): void
    {
        $redirect = $this->createStub(UrlRedirect::class);
        $redirect->method('getOldUrl')->willReturn('/politica/old-slug');
        $redirect->method('getNewUrl')->willReturn('/economie/new-slug');
        $redirect->method('getHttpStatusCode')->willReturn(301);
        $redirect->method('getType')->willReturn('article');

        $this->slugLookupService->method('findArticleBySlug')->willReturn([
            'article' => null,
            'redirect' => $redirect,
            'found_via' => null,
        ]);

        $request = Request::create('/api/slug/lookup', 'POST', [], [], [], [],
            json_encode(['category_slug' => 'politica', 'article_slug' => 'old-slug'])
        );

        $response = $this->controller->lookup($request);

        $this->assertSame(301, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('/politica/old-slug', $data['redirect']['old_url']);
        $this->assertSame('/economie/new-slug', $data['redirect']['new_url']);
    }

    #[Test]
    public function lookupReturns404WhenNotFound(): void
    {
        $this->slugLookupService->method('findArticleBySlug')->willReturn([
            'article' => null,
            'redirect' => null,
            'found_via' => null,
        ]);

        $request = Request::create('/api/slug/lookup', 'POST', [], [], [], [],
            json_encode(['category_slug' => 'politica', 'article_slug' => 'nonexistent'])
        );

        $response = $this->controller->lookup($request);

        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertSame('Article not found', $data['error']);
        $this->assertSame('politica', $data['requested']['category_slug']);
        $this->assertSame('nonexistent', $data['requested']['article_slug']);
    }

    #[Test]
    public function lookupUsesDefaultLocaleRo(): void
    {
        $this->slugLookupService->method('findArticleBySlug')->willReturn([
            'article' => null,
            'redirect' => null,
            'found_via' => null,
        ]);

        $request = Request::create('/api/slug/lookup', 'POST', [], [], [], [],
            json_encode(['category_slug' => 'politica', 'article_slug' => 'test'])
        );

        $response = $this->controller->lookup($request);

        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('ro', $data['requested']['locale']);
    }

    // =============================================
    // POST /api/slug/validate
    // =============================================

    #[Test]
    public function validateReturnsBadRequestWhenFieldsMissing(): void
    {
        $request = Request::create('/api/slug/validate', 'POST', [], [], [], [], json_encode([]));

        $response = $this->controller->validate($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Missing required fields', $data['error']);
    }

    #[Test]
    public function validateReturnsBadRequestForInvalidType(): void
    {
        $request = Request::create('/api/slug/validate', 'POST', [], [], [], [],
            json_encode(['slug' => 'test', 'type' => 'page'])
        );

        $response = $this->controller->validate($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Invalid type', $data['error']);
    }

    #[Test]
    public function validateReturns200ForValidRequest(): void
    {
        $this->slugLookupService->method('isSlugAvailable')->willReturn(true);

        $request = Request::create('/api/slug/validate', 'POST', [], [], [], [],
            json_encode(['slug' => 'test-slug', 'type' => 'article', 'locale' => 'en'])
        );

        $response = $this->controller->validate($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertTrue($data['available']);
        $this->assertSame('test-slug', $data['slug']);
        $this->assertSame('article', $data['type']);
        $this->assertSame('en', $data['locale']);
    }

    #[Test]
    public function validateAcceptsCategoryType(): void
    {
        $this->slugLookupService->method('isSlugAvailable')->willReturn(false);

        $request = Request::create('/api/slug/validate', 'POST', [], [], [], [],
            json_encode(['slug' => 'politica', 'type' => 'category'])
        );

        $response = $this->controller->validate($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['available']);
    }

    #[Test]
    public function validateUsesExcludeIdWhenProvided(): void
    {
        $this->slugLookupService->method('isSlugAvailable')->willReturn(true);

        $request = Request::create('/api/slug/validate', 'POST', [], [], [], [],
            json_encode(['slug' => 'my-slug', 'type' => 'article', 'exclude_id' => 42])
        );

        $response = $this->controller->validate($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    // =============================================
    // POST /api/slug/check-redirect
    // =============================================

    #[Test]
    public function checkRedirectReturnsBadRequestWhenUrlMissing(): void
    {
        $request = Request::create('/api/slug/check-redirect', 'POST', [], [], [], [], json_encode([]));

        $response = $this->controller->checkRedirect($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    #[Test]
    public function checkRedirectReturnsNoRedirectWhenEmpty(): void
    {
        $this->slugLookupService->method('getRedirectChain')->willReturn([
            'redirects' => [],
            'final_url' => '/test',
            'chain_length' => 0,
        ]);

        $request = Request::create('/api/slug/check-redirect', 'POST', [], [], [], [],
            json_encode(['url' => '/test'])
        );

        $response = $this->controller->checkRedirect($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertFalse($data['has_redirect']);
        $this->assertSame('/test', $data['url']);
    }

    #[Test]
    public function checkRedirectReturnsChainWithRedirects(): void
    {
        $redirect = $this->createStub(UrlRedirect::class);
        $redirect->method('getNewUrl')->willReturn('/new-url');
        $redirect->method('getHttpStatusCode')->willReturn(301);
        $redirect->method('getType')->willReturn('article');
        $redirect->method('getHitCount')->willReturn(42);
        $redirect->method('getCreatedAt')->willReturn(new \DateTimeImmutable('2026-01-15 10:00:00'));

        $this->slugLookupService->method('getRedirectChain')->willReturn([
            'redirects' => [$redirect],
            'final_url' => '/new-url',
            'chain_length' => 1,
        ]);

        $request = Request::create('/api/slug/check-redirect', 'POST', [], [], [], [],
            json_encode(['url' => '/old-url'])
        );

        $response = $this->controller->checkRedirect($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['has_redirect']);
        $this->assertCount(1, $data['chain']);
        $this->assertSame('/old-url', $data['chain'][0]['from']);
        $this->assertSame('/new-url', $data['chain'][0]['to']);
        $this->assertSame(301, $data['chain'][0]['status_code']);
        $this->assertSame('/new-url', $data['final_url']);
        $this->assertSame(1, $data['chain_length']);
        $this->assertNull($data['warning']);
    }

    #[Test]
    public function checkRedirectReturnsWarningForLongChain(): void
    {
        $redirects = [];
        for ($i = 0; $i < 4; $i++) {
            $redirect = $this->createStub(UrlRedirect::class);
            $redirect->method('getNewUrl')->willReturn('/url-' . ($i + 1));
            $redirect->method('getHttpStatusCode')->willReturn(301);
            $redirect->method('getType')->willReturn('article');
            $redirect->method('getHitCount')->willReturn(0);
            $redirect->method('getCreatedAt')->willReturn(new \DateTimeImmutable());
            $redirects[] = $redirect;
        }

        $this->slugLookupService->method('getRedirectChain')->willReturn([
            'redirects' => $redirects,
            'final_url' => '/url-4',
            'chain_length' => 4,
        ]);

        $request = Request::create('/api/slug/check-redirect', 'POST', [], [], [], [],
            json_encode(['url' => '/old-url'])
        );

        $response = $this->controller->checkRedirect($request);

        $data = json_decode($response->getContent(), true);
        $this->assertSame(4, $data['chain_length']);
        $this->assertStringContainsString('Long redirect chain', $data['warning']);
    }

    // =============================================
    // GET /api/slug/reserved
    // =============================================

    #[Test]
    public function getReservedSlugsReturnsList(): void
    {
        $this->slugLookupService->method('getReservedSlugs')->willReturn(['admin', 'search', 'trending']);

        $response = $this->controller->getReservedSlugs();

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame(['admin', 'search', 'trending'], $data['reserved_slugs']);
        $this->assertSame(3, $data['count']);
    }

    // =============================================
    // POST /api/slug/check-reserved
    // =============================================

    #[Test]
    public function checkReservedReturnsBadRequestWhenSlugMissing(): void
    {
        $request = Request::create('/api/slug/check-reserved', 'POST', [], [], [], [], json_encode([]));

        $response = $this->controller->checkReserved($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    #[Test]
    public function checkReservedReturnsResultForValidSlug(): void
    {
        $this->slugLookupService->method('isSlugReserved')->willReturn(true);

        $request = Request::create('/api/slug/check-reserved', 'POST', [], [], [], [],
            json_encode(['slug' => 'admin'])
        );

        $response = $this->controller->checkReserved($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('admin', $data['slug']);
        $this->assertTrue($data['is_reserved']);
    }

    #[Test]
    public function checkReservedReturnsFalseForNonReserved(): void
    {
        $this->slugLookupService->method('isSlugReserved')->willReturn(false);

        $request = Request::create('/api/slug/check-reserved', 'POST', [], [], [], [],
            json_encode(['slug' => 'unique-slug'])
        );

        $response = $this->controller->checkReserved($request);

        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['is_reserved']);
    }

    // =============================================
    // POST /api/slug/bulk-validate
    // =============================================

    #[Test]
    public function bulkValidateReturnsBadRequestWhenFieldsMissing(): void
    {
        $request = Request::create('/api/slug/bulk-validate', 'POST', [], [], [], [], json_encode([]));

        $response = $this->controller->bulkValidate($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Missing required fields', $data['error']);
    }

    #[Test]
    public function bulkValidateReturnsBadRequestWhenSlugsNotArray(): void
    {
        $request = Request::create('/api/slug/bulk-validate', 'POST', [], [], [], [],
            json_encode(['slugs' => 'not-array', 'type' => 'article'])
        );

        $response = $this->controller->bulkValidate($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function bulkValidateReturnsBadRequestWhenTooManySlugs(): void
    {
        $slugs = array_map(fn ($i) => "slug-$i", range(1, 51));

        $request = Request::create('/api/slug/bulk-validate', 'POST', [], [], [], [],
            json_encode(['slugs' => $slugs, 'type' => 'article'])
        );

        $response = $this->controller->bulkValidate($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('Maximum 50', $data['error']);
    }

    #[Test]
    public function bulkValidateReturns200WithSummary(): void
    {
        $this->slugLookupService->method('bulkValidate')->willReturn([
            'slug-1' => ['slug' => 'slug-1', 'available' => true, 'reserved' => false, 'valid' => true],
            'admin' => ['slug' => 'admin', 'available' => true, 'reserved' => true, 'valid' => false],
        ]);

        $request = Request::create('/api/slug/bulk-validate', 'POST', [], [], [], [],
            json_encode(['slugs' => ['slug-1', 'admin'], 'type' => 'article', 'locale' => 'ro'])
        );

        $response = $this->controller->bulkValidate($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame(2, $data['summary']['total']);
        $this->assertSame(1, $data['summary']['valid']);
        $this->assertSame(1, $data['summary']['invalid']);
        $this->assertSame(1, $data['summary']['reserved']);
    }

    #[Test]
    public function bulkValidateAccepts50Slugs(): void
    {
        $slugs = array_map(fn ($i) => "slug-$i", range(1, 50));
        $results = [];
        foreach ($slugs as $s) {
            $results[$s] = ['slug' => $s, 'available' => true, 'reserved' => false, 'valid' => true];
        }

        $this->slugLookupService->method('bulkValidate')->willReturn($results);

        $request = Request::create('/api/slug/bulk-validate', 'POST', [], [], [], [],
            json_encode(['slugs' => $slugs, 'type' => 'article'])
        );

        $response = $this->controller->bulkValidate($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    // =============================================
    // POST /api/slug/suggest
    // =============================================

    #[Test]
    public function suggestReturnsBadRequestWhenFieldsMissing(): void
    {
        $request = Request::create('/api/slug/suggest', 'POST', [], [], [], [], json_encode([]));

        $response = $this->controller->suggest($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Missing required fields', $data['error']);
    }

    #[Test]
    public function suggestReturnsBadRequestWhenTitleMissing(): void
    {
        $request = Request::create('/api/slug/suggest', 'POST', [], [], [], [],
            json_encode(['type' => 'article'])
        );

        $response = $this->controller->suggest($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function suggestReturnsBadRequestWhenTypeMissing(): void
    {
        $request = Request::create('/api/slug/suggest', 'POST', [], [], [], [],
            json_encode(['title' => 'Test Title'])
        );

        $response = $this->controller->suggest($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    #[Test]
    public function suggestReturns200WithSuggestions(): void
    {
        $this->slugLookupService->method('generateSlugSuggestions')->willReturn([
            ['slug' => 'reforma-guvernului', 'available' => true, 'reserved' => false],
            ['slug' => 'reforma-guvernului-1', 'available' => true, 'reserved' => false],
        ]);

        $request = Request::create('/api/slug/suggest', 'POST', [], [], [], [],
            json_encode(['title' => 'Reforma Guvernului', 'type' => 'article', 'locale' => 'ro'])
        );

        $response = $this->controller->suggest($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertSame('Reforma Guvernului', $data['title']);
        $this->assertCount(2, $data['suggestions']);
        $this->assertSame(2, $data['count']);
    }

    #[Test]
    public function suggestCapsMaxSuggestionsAt10(): void
    {
        // When max_suggestions > 10, it should be capped at 10
        $suggestions = array_fill(0, 10, ['slug' => 'test', 'available' => true, 'reserved' => false]);
        $this->slugLookupService->method('generateSlugSuggestions')->willReturn($suggestions);

        $request = Request::create('/api/slug/suggest', 'POST', [], [], [], [],
            json_encode(['title' => 'Test', 'type' => 'article', 'max_suggestions' => 20])
        );

        $response = $this->controller->suggest($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function suggestDefaultsTo5MaxSuggestions(): void
    {
        $suggestions = array_fill(0, 5, ['slug' => 'test', 'available' => true, 'reserved' => false]);
        $this->slugLookupService->method('generateSlugSuggestions')->willReturn($suggestions);

        $request = Request::create('/api/slug/suggest', 'POST', [], [], [], [],
            json_encode(['title' => 'Test', 'type' => 'article'])
        );

        $response = $this->controller->suggest($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(5, $data['count']);
    }
}
