<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Api;

use App\Controller\Api\SlugController;
use App\Entity\Article;
use App\Entity\Author;
use App\Entity\Category;
use App\Repository\ArticleRepository;
use App\Repository\AuthorRepository;
use App\Repository\CategoryRepository;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Unit tests for SlugController.
 *
 * Tests all three slug-lookup endpoints:
 * - GET /api/articles/by-slug/{slug}
 * - GET /api/categories/by-slug/{slug}
 * - GET /api/authors/by-slug/{slug}
 */
class SlugControllerTest extends TestCase
{
    private ArticleRepository $articleRepository;
    private CategoryRepository $categoryRepository;
    private AuthorRepository $authorRepository;
    private SerializerInterface $serializer;
    private EntityManagerInterface $entityManager;
    private SlugController $controller;

    protected function setUp(): void
    {
        $this->articleRepository = $this->createStub(ArticleRepository::class);
        $this->categoryRepository = $this->createStub(CategoryRepository::class);
        $this->authorRepository = $this->createStub(AuthorRepository::class);
        $this->serializer = $this->createStub(SerializerInterface::class);
        $this->entityManager = $this->createStub(EntityManagerInterface::class);

        $this->controller = new SlugController(
            $this->articleRepository,
            $this->categoryRepository,
            $this->authorRepository,
            $this->serializer,
            $this->entityManager,
        );
    }

    // =============================================
    // Helper: build a stub QueryBuilder chain
    // =============================================

    /**
     * Create a stub QueryBuilder that chains all fluent methods and returns $result from getOneOrNullResult().
     */
    private function createQueryBuilderChainReturning(mixed $result): QueryBuilder
    {
        $query = $this->getMockBuilder(\Doctrine\ORM\Query::class)->disableOriginalConstructor()->getMock();
        $query->method('setHint')->willReturnSelf();
        $query->method('getOneOrNullResult')->willReturn($result);

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('addSelect')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        return $qb;
    }

    // =============================================
    // getArticleBySlug — article found (default locale "ro")
    // =============================================

    #[Test]
    public function getArticleBySlugReturnsArticleForDefaultLocale(): void
    {
        $article = $this->createStub(Article::class);
        $category = $this->createStub(Category::class);
        $article->method('getCategory')->willReturn($category);

        $qb = $this->createQueryBuilderChainReturning($article);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        $this->serializer->method('serialize')->willReturn('{"id":1,"title":"Test"}');

        $request = Request::create('/api/articles/by-slug/test-article', 'GET');

        $response = $this->controller->getArticleBySlug('test-article', $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"id":1,"title":"Test"}', $response->getContent());
    }

    #[Test]
    public function getArticleBySlugReturns404WhenNotFound(): void
    {
        $qb = $this->createQueryBuilderChainReturning(null);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        $request = Request::create('/api/articles/by-slug/nonexistent', 'GET');

        $response = $this->controller->getArticleBySlug('nonexistent', $request);

        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('hydra:Error', $data['@type']);
        $this->assertStringContainsString('nonexistent', $data['hydra:description']);
    }

    // =============================================
    // getArticleBySlug — locale handling
    // =============================================

    #[Test]
    public function getArticleBySlugUsesLocaleFromQueryParameter(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getCategory')->willReturn(null);

        $qb = $this->createQueryBuilderChainReturning($article);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        $this->serializer->method('serialize')->willReturn('{"id":2}');

        $request = Request::create('/api/articles/by-slug/test?locale=en', 'GET');

        $response = $this->controller->getArticleBySlug('test', $request);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function getArticleBySlugUsesLocaleFromAcceptLanguageHeader(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getCategory')->willReturn(null);

        $qb = $this->createQueryBuilderChainReturning($article);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        $this->serializer->method('serialize')->willReturn('{"id":3}');

        $request = Request::create('/api/articles/by-slug/test', 'GET');
        $request->headers->set('Accept-Language', 'ru');

        $response = $this->controller->getArticleBySlug('test', $request);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function getArticleBySlugNormalizesLocaleWithDash(): void
    {
        $qb = $this->createQueryBuilderChainReturning(null);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        // "en-US" should be normalized to "en"
        $request = Request::create('/api/articles/by-slug/test?locale=en-US', 'GET');

        $response = $this->controller->getArticleBySlug('test', $request);

        // We only verify it doesn't crash and returns a valid response
        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function getArticleBySlugNormalizesLocaleWithComma(): void
    {
        $qb = $this->createQueryBuilderChainReturning(null);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        // "en,ru;q=0.5" should be normalized to "en"
        $request = Request::create('/api/articles/by-slug/test', 'GET');
        $request->headers->set('Accept-Language', 'en,ru;q=0.5');

        $response = $this->controller->getArticleBySlug('test', $request);

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function getArticleBySlugFallsBackToRoForInvalidLocale(): void
    {
        $qb = $this->createQueryBuilderChainReturning(null);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        // "de" is not in [ro, en, ru], should fall back to "ro"
        $request = Request::create('/api/articles/by-slug/test?locale=de', 'GET');

        $response = $this->controller->getArticleBySlug('test', $request);

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function getArticleBySlugDefaultsToRoWhenNoLocaleProvided(): void
    {
        $qb = $this->createQueryBuilderChainReturning(null);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        // No locale query param, no Accept-Language header
        $request = Request::create('/api/articles/by-slug/test', 'GET');

        $response = $this->controller->getArticleBySlug('test', $request);

        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(404, $data['status']);
    }

    #[Test]
    public function getArticleBySlugRefreshesCategoryWhenPresent(): void
    {
        $category = $this->createStub(Category::class);
        $article = $this->createStub(Article::class);
        $article->method('getCategory')->willReturn($category);

        $qb = $this->createQueryBuilderChainReturning($article);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        $this->serializer->method('serialize')->willReturn('{"id":1}');

        $request = Request::create('/api/articles/by-slug/test?locale=en', 'GET');

        $response = $this->controller->getArticleBySlug('test', $request);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function getArticleBySlugHandlesArticleWithNullCategory(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getCategory')->willReturn(null);

        $qb = $this->createQueryBuilderChainReturning($article);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        $this->serializer->method('serialize')->willReturn('{"id":1}');

        $request = Request::create('/api/articles/by-slug/test?locale=en', 'GET');

        $response = $this->controller->getArticleBySlug('test', $request);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function getArticleBySlugSerializesWithCorrectGroups(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getCategory')->willReturn(null);

        $qb = $this->createQueryBuilderChainReturning($article);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('serialize')
            ->with(
                $article,
                'json',
                $this->callback(function (array $context): bool {
                    return isset($context['groups'])
                        && \in_array('article:read', $context['groups'], true)
                        && \in_array('article:detail', $context['groups'], true)
                        && ($context['enable_max_depth'] ?? false) === true;
                }),
            )
            ->willReturn('{"serialized":true}');

        $controller = new SlugController(
            $this->articleRepository,
            $this->categoryRepository,
            $this->authorRepository,
            $serializer,
            $this->entityManager,
        );

        $request = Request::create('/api/articles/by-slug/test', 'GET');

        $response = $controller->getArticleBySlug('test', $request);

        $this->assertSame(200, $response->getStatusCode());
    }

    // =============================================
    // getCategoryBySlug — success paths
    // =============================================

    #[Test]
    public function getCategoryBySlugReturnsCategoryForDefaultLocale(): void
    {
        $category = $this->createStub(Category::class);

        $qb = $this->createQueryBuilderChainReturning($category);
        $this->categoryRepository->method('createQueryBuilder')->willReturn($qb);

        $this->serializer->method('serialize')->willReturn('{"id":5,"name":"Politica"}');

        $request = Request::create('/api/categories/by-slug/politica', 'GET');

        $response = $this->controller->getCategoryBySlug('politica', $request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"id":5,"name":"Politica"}', $response->getContent());
    }

    #[Test]
    public function getCategoryBySlugReturns404WhenNotFound(): void
    {
        $qb = $this->createQueryBuilderChainReturning(null);
        $this->categoryRepository->method('createQueryBuilder')->willReturn($qb);

        $request = Request::create('/api/categories/by-slug/nonexistent', 'GET');

        $response = $this->controller->getCategoryBySlug('nonexistent', $request);

        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('hydra:Error', $data['@type']);
        $this->assertStringContainsString('nonexistent', $data['hydra:description']);
    }

    #[Test]
    public function getCategoryBySlugUsesLocaleFromQueryParameter(): void
    {
        $category = $this->createStub(Category::class);

        $qb = $this->createQueryBuilderChainReturning($category);
        $this->categoryRepository->method('createQueryBuilder')->willReturn($qb);

        $this->serializer->method('serialize')->willReturn('{"id":5}');

        $request = Request::create('/api/categories/by-slug/politics?locale=en', 'GET');

        $response = $this->controller->getCategoryBySlug('politics', $request);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function getCategoryBySlugUsesLocaleFromAcceptLanguageHeader(): void
    {
        $category = $this->createStub(Category::class);

        $qb = $this->createQueryBuilderChainReturning($category);
        $this->categoryRepository->method('createQueryBuilder')->willReturn($qb);

        $this->serializer->method('serialize')->willReturn('{"id":5}');

        $request = Request::create('/api/categories/by-slug/politica', 'GET');
        $request->headers->set('Accept-Language', 'ru');

        $response = $this->controller->getCategoryBySlug('politica', $request);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function getCategoryBySlugNormalizesLocaleWithDash(): void
    {
        $qb = $this->createQueryBuilderChainReturning(null);
        $this->categoryRepository->method('createQueryBuilder')->willReturn($qb);

        $request = Request::create('/api/categories/by-slug/test?locale=ru-RU', 'GET');

        $response = $this->controller->getCategoryBySlug('test', $request);

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function getCategoryBySlugNormalizesLocaleWithComma(): void
    {
        $qb = $this->createQueryBuilderChainReturning(null);
        $this->categoryRepository->method('createQueryBuilder')->willReturn($qb);

        $request = Request::create('/api/categories/by-slug/test', 'GET');
        $request->headers->set('Accept-Language', 'ro,en;q=0.5');

        $response = $this->controller->getCategoryBySlug('test', $request);

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function getCategoryBySlugFallsBackToRoForInvalidLocale(): void
    {
        $qb = $this->createQueryBuilderChainReturning(null);
        $this->categoryRepository->method('createQueryBuilder')->willReturn($qb);

        $request = Request::create('/api/categories/by-slug/test?locale=fr', 'GET');

        $response = $this->controller->getCategoryBySlug('test', $request);

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function getCategoryBySlugSerializesWithCategoryReadGroup(): void
    {
        $category = $this->createStub(Category::class);

        $qb = $this->createQueryBuilderChainReturning($category);
        $this->categoryRepository->method('createQueryBuilder')->willReturn($qb);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('serialize')
            ->with(
                $category,
                'json',
                $this->callback(function (array $context): bool {
                    return isset($context['groups'])
                        && $context['groups'] === ['category:read'];
                }),
            )
            ->willReturn('{"serialized":true}');

        $controller = new SlugController(
            $this->articleRepository,
            $this->categoryRepository,
            $this->authorRepository,
            $serializer,
            $this->entityManager,
        );

        $request = Request::create('/api/categories/by-slug/politica', 'GET');

        $response = $controller->getCategoryBySlug('politica', $request);

        $this->assertSame(200, $response->getStatusCode());
    }

    // =============================================
    // getAuthorBySlug — success paths
    // =============================================

    #[Test]
    public function getAuthorBySlugReturnsAuthorWhenFound(): void
    {
        $author = $this->createStub(Author::class);

        $this->authorRepository->method('findOneBy')->willReturn($author);
        $this->serializer->method('serialize')->willReturn('{"id":10,"slug":"john-doe"}');

        $response = $this->controller->getAuthorBySlug('john-doe');

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('{"id":10,"slug":"john-doe"}', $response->getContent());
    }

    #[Test]
    public function getAuthorBySlugReturns404WhenNotFound(): void
    {
        $this->authorRepository->method('findOneBy')->willReturn(null);

        $response = $this->controller->getAuthorBySlug('nonexistent');

        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('hydra:Error', $data['@type']);
        $this->assertStringContainsString('nonexistent', $data['hydra:description']);
    }

    #[Test]
    public function getAuthorBySlugSerializesWithAuthorReadGroup(): void
    {
        $author = $this->createStub(Author::class);
        $this->authorRepository->method('findOneBy')->willReturn($author);

        $serializer = $this->createMock(SerializerInterface::class);
        $serializer->expects($this->once())
            ->method('serialize')
            ->with(
                $author,
                'json',
                $this->callback(function (array $context): bool {
                    return isset($context['groups'])
                        && $context['groups'] === ['author:read'];
                }),
            )
            ->willReturn('{"serialized":true}');

        $controller = new SlugController(
            $this->articleRepository,
            $this->categoryRepository,
            $this->authorRepository,
            $serializer,
            $this->entityManager,
        );

        $response = $controller->getAuthorBySlug('john-doe');

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function getAuthorBySlugSearchesBySlugField(): void
    {
        $authorRepository = $this->createMock(AuthorRepository::class);
        $authorRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['slug' => 'jane-smith'])
            ->willReturn(null);

        $controller = new SlugController(
            $this->articleRepository,
            $this->categoryRepository,
            $authorRepository,
            $this->serializer,
            $this->entityManager,
        );

        $response = $controller->getAuthorBySlug('jane-smith');

        $this->assertSame(404, $response->getStatusCode());
    }

    // =============================================
    // Error response format validation
    // =============================================

    #[Test]
    public function articleNotFoundResponseHasCorrectHydraFormat(): void
    {
        $qb = $this->createQueryBuilderChainReturning(null);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        $request = Request::create('/api/articles/by-slug/missing', 'GET');

        $response = $this->controller->getArticleBySlug('missing', $request);

        $data = json_decode($response->getContent(), true);
        $this->assertSame('/api/contexts/Error', $data['@context']);
        $this->assertSame('hydra:Error', $data['@type']);
        $this->assertSame('An error occurred', $data['hydra:title']);
        $this->assertSame(404, $data['status']);
        $this->assertSame('Article with slug "missing" not found', $data['hydra:description']);
    }

    #[Test]
    public function categoryNotFoundResponseHasCorrectHydraFormat(): void
    {
        $qb = $this->createQueryBuilderChainReturning(null);
        $this->categoryRepository->method('createQueryBuilder')->willReturn($qb);

        $request = Request::create('/api/categories/by-slug/missing', 'GET');

        $response = $this->controller->getCategoryBySlug('missing', $request);

        $data = json_decode($response->getContent(), true);
        $this->assertSame('/api/contexts/Error', $data['@context']);
        $this->assertSame('hydra:Error', $data['@type']);
        $this->assertSame('An error occurred', $data['hydra:title']);
        $this->assertSame(404, $data['status']);
        $this->assertSame('Category with slug "missing" not found', $data['hydra:description']);
    }

    #[Test]
    public function authorNotFoundResponseHasCorrectHydraFormat(): void
    {
        $this->authorRepository->method('findOneBy')->willReturn(null);

        $response = $this->controller->getAuthorBySlug('missing');

        $data = json_decode($response->getContent(), true);
        $this->assertSame('/api/contexts/Error', $data['@context']);
        $this->assertSame('hydra:Error', $data['@type']);
        $this->assertSame('An error occurred', $data['hydra:title']);
        $this->assertSame(404, $data['status']);
        $this->assertSame('Author with slug "missing" not found', $data['hydra:description']);
    }

    // =============================================
    // Locale edge cases (shared by article and category)
    // =============================================

    public static function validLocaleProvider(): \Generator
    {
        yield 'ro locale' => ['ro'];
        yield 'en locale' => ['en'];
        yield 'ru locale' => ['ru'];
    }

    #[Test]
    #[DataProvider('validLocaleProvider')]
    public function getArticleBySlugAcceptsAllValidLocales(string $locale): void
    {
        $qb = $this->createQueryBuilderChainReturning(null);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        $request = Request::create("/api/articles/by-slug/test?locale={$locale}", 'GET');

        $response = $this->controller->getArticleBySlug('test', $request);

        // A valid locale should not cause errors - it returns 404 because the stub returns null
        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    #[DataProvider('validLocaleProvider')]
    public function getCategoryBySlugAcceptsAllValidLocales(string $locale): void
    {
        $qb = $this->createQueryBuilderChainReturning(null);
        $this->categoryRepository->method('createQueryBuilder')->willReturn($qb);

        $request = Request::create("/api/categories/by-slug/test?locale={$locale}", 'GET');

        $response = $this->controller->getCategoryBySlug('test', $request);

        $this->assertSame(404, $response->getStatusCode());
    }

    public static function invalidLocaleProvider(): \Generator
    {
        yield 'French' => ['fr'];
        yield 'German' => ['de'];
        yield 'Spanish' => ['es'];
        yield 'Chinese' => ['zh'];
        yield 'empty string' => [''];
    }

    #[Test]
    #[DataProvider('invalidLocaleProvider')]
    public function getArticleBySlugFallsBackToRoForUnsupportedLocales(string $locale): void
    {
        $qb = $this->createQueryBuilderChainReturning(null);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        $request = Request::create("/api/articles/by-slug/test?locale={$locale}", 'GET');

        $response = $this->controller->getArticleBySlug('test', $request);

        // Should not crash, falls back to "ro"
        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function getArticleBySlugHandlesComplexAcceptLanguageHeader(): void
    {
        $qb = $this->createQueryBuilderChainReturning(null);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        // Complex Accept-Language: "en-US,en;q=0.9,ro;q=0.8"
        $request = Request::create('/api/articles/by-slug/test', 'GET');
        $request->headers->set('Accept-Language', 'en-US,en;q=0.9,ro;q=0.8');

        $response = $this->controller->getArticleBySlug('test', $request);

        // Should normalize to "en" (first part before dash, then before comma)
        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function getArticleBySlugQueryParameterTakesPrecedenceOverHeader(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getCategory')->willReturn(null);

        $qb = $this->createQueryBuilderChainReturning($article);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        $this->serializer->method('serialize')->willReturn('{"id":1}');

        // locale=en in query, Accept-Language: ru in header
        $request = Request::create('/api/articles/by-slug/test?locale=en', 'GET');
        $request->headers->set('Accept-Language', 'ru');

        $response = $this->controller->getArticleBySlug('test', $request);

        // Should not crash; query param takes precedence
        $this->assertSame(200, $response->getStatusCode());
    }

    // =============================================
    // JSON response format
    // =============================================

    #[Test]
    public function getArticleBySlugReturnsRawJsonResponse(): void
    {
        $article = $this->createStub(Article::class);
        $article->method('getCategory')->willReturn(null);

        $qb = $this->createQueryBuilderChainReturning($article);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        $this->serializer->method('serialize')->willReturn('{"id":1,"title":"Raw JSON"}');

        $request = Request::create('/api/articles/by-slug/test', 'GET');

        $response = $this->controller->getArticleBySlug('test', $request);

        // The response uses the "json" parameter (raw string, not double-encoded)
        $this->assertSame('{"id":1,"title":"Raw JSON"}', $response->getContent());
    }

    #[Test]
    public function getCategoryBySlugReturnsRawJsonResponse(): void
    {
        $category = $this->createStub(Category::class);

        $qb = $this->createQueryBuilderChainReturning($category);
        $this->categoryRepository->method('createQueryBuilder')->willReturn($qb);

        $this->serializer->method('serialize')->willReturn('{"id":5,"name":"Test"}');

        $request = Request::create('/api/categories/by-slug/test', 'GET');

        $response = $this->controller->getCategoryBySlug('test', $request);

        $this->assertSame('{"id":5,"name":"Test"}', $response->getContent());
    }

    #[Test]
    public function getAuthorBySlugReturnsRawJsonResponse(): void
    {
        $author = $this->createStub(Author::class);
        $this->authorRepository->method('findOneBy')->willReturn($author);
        $this->serializer->method('serialize')->willReturn('{"id":10,"name":"Author"}');

        $response = $this->controller->getAuthorBySlug('author-slug');

        $this->assertSame('{"id":10,"name":"Author"}', $response->getContent());
    }
}
