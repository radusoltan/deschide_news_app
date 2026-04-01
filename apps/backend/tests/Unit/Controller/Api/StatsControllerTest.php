<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller\Api;

use App\Controller\Api\StatsController;
use App\Entity\Article;
use App\Entity\ArticleStatsDaily;
use App\Entity\Category;
use App\Entity\SiteStatsDaily;
use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use App\Repository\ArticleStatsDailyRepository;
use App\Repository\CategoryRepository;
use App\Repository\SiteStatsDailyRepository;
use App\Service\PerformanceService;
use DateTime;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for StatsController.
 *
 * Tests all 6 public endpoints:
 * - articleStats (GET /api/admin/stats/article/{id})
 * - trending (GET /api/admin/stats/trending)
 * - siteStats (GET /api/admin/stats/site)
 * - realtime (GET /api/admin/stats/realtime)
 * - categoryStats (GET /api/admin/stats/categories)
 * - articleCounts (GET /api/admin/stats/article-counts)
 */
class StatsControllerTest extends TestCase
{
    private PerformanceService $performance;
    private ArticleStatsDailyRepository $articleStatsRepository;
    private SiteStatsDailyRepository $siteStatsRepository;
    private ArticleRepository $articleRepository;
    private CategoryRepository $categoryRepository;
    private StatsController $controller;

    protected function setUp(): void
    {
        $this->performance = $this->createStub(PerformanceService::class);
        $this->articleStatsRepository = $this->createStub(ArticleStatsDailyRepository::class);
        $this->siteStatsRepository = $this->createStub(SiteStatsDailyRepository::class);
        $this->articleRepository = $this->createStub(ArticleRepository::class);
        $this->categoryRepository = $this->createStub(CategoryRepository::class);

        $this->controller = new StatsController(
            $this->performance,
            $this->articleStatsRepository,
            $this->siteStatsRepository,
            $this->articleRepository,
            $this->categoryRepository
        );

        // AbstractController requires a container
        $container = $this->createStub(ContainerInterface::class);
        $container->method('has')->willReturnCallback(fn (string $id): bool => $id === 'parameter_bag');
        $paramBag = $this->createStub(ContainerBagInterface::class);
        $paramBag->method('get')->willReturnMap([
            ['kernel.debug', false],
        ]);
        $container->method('get')->willReturnCallback(
            fn (string $id) => $id === 'parameter_bag' ? $paramBag : null
        );
        $this->controller->setContainer($container);
    }

    // =============================================
    // Helper methods
    // =============================================

    private function createArticleStub(int $id, string $title = 'Test Article', ?Category $category = null, ?string $slug = null): Article
    {
        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn($id);
        $article->method('getTitle')->willReturn($title);
        $article->method('getSlug')->willReturn($slug ?? 'test-article');
        $article->method('getCategory')->willReturn($category);
        $article->method('getPublishedAt')->willReturn(new \DateTimeImmutable('2026-03-15T10:00:00+00:00'));

        return $article;
    }

    private function createCategoryStub(int $id, string $title = 'Test Category', string $slug = 'test-category'): Category
    {
        $category = $this->createStub(Category::class);
        $category->method('getId')->willReturn($id);
        $category->method('getTitle')->willReturn($title);
        $category->method('getSlug')->willReturn($slug);

        return $category;
    }

    private function createArticleStatsDailyStub(string $date, int $views, int $uniqueVisitors, ?int $avgReadingTime = 120, ?float $completionRate = 0.75): ArticleStatsDaily
    {
        $stat = $this->createStub(ArticleStatsDaily::class);
        $stat->method('getDate')->willReturn(new DateTime($date));
        $stat->method('getViews')->willReturn($views);
        $stat->method('getUniqueVisitors')->willReturn($uniqueVisitors);
        $stat->method('getAvgReadingTime')->willReturn($avgReadingTime);
        $stat->method('getCompletionRate')->willReturn($completionRate);

        return $stat;
    }

    private function createSiteStatsDailyStub(string $date, int $totalVisits, int $uniqueVisitors, int $newVisitors = 50, ?float $bounceRate = 45.5, ?int $avgSessionDuration = 180): SiteStatsDaily
    {
        $stat = $this->createStub(SiteStatsDaily::class);
        $stat->method('getDate')->willReturn(new DateTime($date));
        $stat->method('getTotalVisits')->willReturn($totalVisits);
        $stat->method('getUniqueVisitors')->willReturn($uniqueVisitors);
        $stat->method('getNewVisitors')->willReturn($newVisitors);
        $stat->method('getBounceRate')->willReturn($bounceRate);
        $stat->method('getAvgSessionDuration')->willReturn($avgSessionDuration);

        return $stat;
    }

    private function decodeResponse(JsonResponse $response): array
    {
        return json_decode($response->getContent(), true);
    }

    /**
     * Create a stub QueryBuilder that returns given results.
     * Supports chaining: leftJoin, addSelect, where, andWhere, select,
     * setParameter, groupBy, orderBy, setHint, getQuery, getResult, expr, in.
     */
    private function createQueryBuilderStub(array $results): QueryBuilder
    {
        $query = $this->getMockBuilder(\Doctrine\ORM\Query::class)->disableOriginalConstructor()->getMock();
        $query->method('getResult')->willReturn($results);
        $query->method('setHint')->willReturnSelf();

        $expr = $this->createStub(Expr::class);
        $expr->method('in')->willReturn(new Expr\Func('a.id IN', [':ids']));

        $qb = $this->createStub(QueryBuilder::class);
        $qb->method('leftJoin')->willReturnSelf();
        $qb->method('addSelect')->willReturnSelf();
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('groupBy')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('setHint')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        $qb->method('expr')->willReturn($expr);

        return $qb;
    }

    // =============================================
    // articleStats()
    // =============================================

    #[Test]
    public function articleStatsReturnsCachedDataWhenAvailable(): void
    {
        $cachedData = [
            'article_id' => 42,
            'title' => 'Cached Article',
            'current_views' => 100,
            'stats' => [],
        ];
        $this->performance->method('getCached')->willReturn($cachedData);

        $request = Request::create('/api/admin/stats/article/42', 'GET', ['range' => '7days']);
        $response = $this->controller->articleStats(42, $request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($cachedData, $this->decodeResponse($response));
    }

    #[Test]
    public function articleStatsReturns404WhenArticleNotFound(): void
    {
        $this->performance->method('getCached')->willReturn(null);
        $this->articleRepository->method('find')->willReturn(null);

        $request = Request::create('/api/admin/stats/article/999', 'GET');
        $response = $this->controller->articleStats(999, $request);

        $this->assertSame(404, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertSame('Article not found', $data['error']);
    }

    #[Test]
    public function articleStatsReturnsFormattedStatsForExistingArticle(): void
    {
        $this->performance->method('getCached')->willReturn(null);

        $article = $this->createArticleStub(42, 'Test Article Title');
        $this->articleRepository->method('find')->willReturn($article);

        $stats = [
            $this->createArticleStatsDailyStub('2026-03-20', 150, 100, 120, 0.80),
            $this->createArticleStatsDailyStub('2026-03-19', 200, 130, 90, 0.65),
        ];
        $this->articleStatsRepository->method('findByArticleAndDateRange')->willReturn($stats);
        $this->performance->method('getArticleViews')->willReturn(350);

        $request = Request::create('/api/admin/stats/article/42', 'GET', ['range' => '7days']);
        $response = $this->controller->articleStats(42, $request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);

        $this->assertSame(42, $data['article_id']);
        $this->assertSame('Test Article Title', $data['title']);
        $this->assertSame(350, $data['current_views']);
        $this->assertCount(2, $data['stats']);

        $this->assertSame('2026-03-20', $data['stats'][0]['date']);
        $this->assertSame(150, $data['stats'][0]['views']);
        $this->assertSame(100, $data['stats'][0]['unique_visitors']);
        $this->assertSame(120, $data['stats'][0]['avg_reading_time']);
        $this->assertSame(0.80, $data['stats'][0]['completion_rate']);
    }

    #[Test]
    public function articleStatsUsesDefaultRangeWhenNotSpecified(): void
    {
        $this->performance->method('getCached')->willReturn(null);

        $article = $this->createArticleStub(1);
        $this->articleRepository->method('find')->willReturn($article);
        $this->articleStatsRepository->method('findByArticleAndDateRange')->willReturn([]);
        $this->performance->method('getArticleViews')->willReturn(0);

        // No range parameter - should default to '7days'
        $request = Request::create('/api/admin/stats/article/1', 'GET');
        $response = $this->controller->articleStats(1, $request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertSame(1, $data['article_id']);
        $this->assertSame([], $data['stats']);
    }

    #[Test]
    public function articleStatsReturnsEmptyStatsArrayWhenNoData(): void
    {
        $this->performance->method('getCached')->willReturn(null);

        $article = $this->createArticleStub(10, 'No Stats Article');
        $this->articleRepository->method('find')->willReturn($article);
        $this->articleStatsRepository->method('findByArticleAndDateRange')->willReturn([]);
        $this->performance->method('getArticleViews')->willReturn(0);

        $request = Request::create('/api/admin/stats/article/10', 'GET', ['range' => '30days']);
        $response = $this->controller->articleStats(10, $request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertSame(10, $data['article_id']);
        $this->assertSame(0, $data['current_views']);
        $this->assertEmpty($data['stats']);
    }

    #[Test]
    public function articleStatsHandlesNullReadingTimeAndCompletionRate(): void
    {
        $this->performance->method('getCached')->willReturn(null);

        $article = $this->createArticleStub(5);
        $this->articleRepository->method('find')->willReturn($article);

        $stats = [
            $this->createArticleStatsDailyStub('2026-03-20', 50, 30, null, null),
        ];
        $this->articleStatsRepository->method('findByArticleAndDateRange')->willReturn($stats);
        $this->performance->method('getArticleViews')->willReturn(50);

        $request = Request::create('/api/admin/stats/article/5', 'GET');
        $response = $this->controller->articleStats(5, $request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertNull($data['stats'][0]['avg_reading_time']);
        $this->assertNull($data['stats'][0]['completion_rate']);
    }

    #[Test]
    #[DataProvider('dateRangeProvider')]
    public function articleStatsAcceptsVariousDateRanges(string $range): void
    {
        $this->performance->method('getCached')->willReturn(null);

        $article = $this->createArticleStub(1);
        $this->articleRepository->method('find')->willReturn($article);
        $this->articleStatsRepository->method('findByArticleAndDateRange')->willReturn([]);
        $this->performance->method('getArticleViews')->willReturn(0);

        $request = Request::create('/api/admin/stats/article/1', 'GET', ['range' => $range]);
        $response = $this->controller->articleStats(1, $request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public static function dateRangeProvider(): array
    {
        return [
            'today' => ['today'],
            'yesterday' => ['yesterday'],
            '7days' => ['7days'],
            '30days' => ['30days'],
            'unknown range falls back to 7days' => ['unknown_range'],
        ];
    }

    // =============================================
    // trending()
    // =============================================

    #[Test]
    public function trendingReturnsCachedDataWhenAvailable(): void
    {
        $cachedData = [
            ['id' => 1, 'title' => 'Cached Trending', 'views_24h' => 500],
        ];
        $this->performance->method('getCached')->willReturn($cachedData);

        $request = Request::create('/api/admin/stats/trending', 'GET');
        $response = $this->controller->trending($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($cachedData, $this->decodeResponse($response));
    }

    #[Test]
    public function trendingReturnsEmptyArrayWhenNoTrendingArticles(): void
    {
        $this->performance->method('getCached')->willReturn(null);
        $this->performance->method('getTrendingArticles')->willReturn([]);

        $request = Request::create('/api/admin/stats/trending', 'GET');
        $response = $this->controller->trending($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $this->decodeResponse($response));
    }

    #[Test]
    public function trendingReturnsFormattedArticlesWithCategories(): void
    {
        $this->performance->method('getCached')->willReturn(null);
        $this->performance->method('getTrendingArticles')->willReturn([
            ['article_id' => 10, 'views' => 500],
            ['article_id' => 20, 'views' => 300],
        ]);

        $category = $this->createCategoryStub(1, 'Politica', 'politica');
        $article10 = $this->createArticleStub(10, 'Trending 1', $category, 'trending-1');
        $article20 = $this->createArticleStub(20, 'Trending 2', null, 'trending-2');

        $qb = $this->createQueryBuilderStub([$article10, $article20]);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        $request = Request::create('/api/admin/stats/trending', 'GET', ['limit' => '10']);
        $request->headers->set('Accept-Language', 'ro');
        $response = $this->controller->trending($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);

        $this->assertCount(2, $data);

        // First article with category
        $this->assertSame(10, $data[0]['id']);
        $this->assertSame('Trending 1', $data[0]['title']);
        $this->assertSame('trending-1', $data[0]['slug']);
        $this->assertSame(500, $data[0]['views_24h']);
        $this->assertNotNull($data[0]['category']);
        $this->assertSame(1, $data[0]['category']['id']);
        $this->assertSame('Politica', $data[0]['category']['name']);
        $this->assertSame('politica', $data[0]['category']['slug']);

        // Second article without category
        $this->assertSame(20, $data[1]['id']);
        $this->assertNull($data[1]['category']);
        $this->assertSame(300, $data[1]['views_24h']);
    }

    #[Test]
    public function trendingPreservesRedisOrderingEvenWhenDbReturnsDifferentOrder(): void
    {
        $this->performance->method('getCached')->willReturn(null);
        $this->performance->method('getTrendingArticles')->willReturn([
            ['article_id' => 20, 'views' => 800],
            ['article_id' => 10, 'views' => 300],
        ]);

        // DB returns articles in different order (by id)
        $article10 = $this->createArticleStub(10, 'Article 10');
        $article20 = $this->createArticleStub(20, 'Article 20');

        $qb = $this->createQueryBuilderStub([$article10, $article20]);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        $request = Request::create('/api/admin/stats/trending', 'GET');
        $response = $this->controller->trending($request);

        $data = $this->decodeResponse($response);
        // Should follow Redis order (article 20 first, then article 10)
        $this->assertSame(20, $data[0]['id']);
        $this->assertSame(800, $data[0]['views_24h']);
        $this->assertSame(10, $data[1]['id']);
        $this->assertSame(300, $data[1]['views_24h']);
    }

    #[Test]
    public function trendingSkipsArticlesNotFoundInDatabase(): void
    {
        $this->performance->method('getCached')->willReturn(null);
        $this->performance->method('getTrendingArticles')->willReturn([
            ['article_id' => 10, 'views' => 500],
            ['article_id' => 999, 'views' => 300], // This ID not in DB
        ]);

        $article10 = $this->createArticleStub(10, 'Found Article');
        // Only article 10 returned from DB, article 999 is missing
        $qb = $this->createQueryBuilderStub([$article10]);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        $request = Request::create('/api/admin/stats/trending', 'GET');
        $response = $this->controller->trending($request);

        $data = $this->decodeResponse($response);
        $this->assertCount(1, $data);
        $this->assertSame(10, $data[0]['id']);
    }

    #[Test]
    public function trendingUsesDefaultLocaleWhenNotProvided(): void
    {
        $this->performance->method('getCached')->willReturn(null);
        $this->performance->method('getTrendingArticles')->willReturn([
            ['article_id' => 1, 'views' => 100],
        ]);

        $article = $this->createArticleStub(1);
        $qb = $this->createQueryBuilderStub([$article]);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        // No Accept-Language header - defaults to 'ro'
        $request = Request::create('/api/admin/stats/trending', 'GET');
        $response = $this->controller->trending($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertCount(1, $data);
    }

    #[Test]
    public function trendingUsesCustomLimit(): void
    {
        $this->performance->method('getCached')->willReturn(null);
        $this->performance->method('getTrendingArticles')->willReturn([]);

        $request = Request::create('/api/admin/stats/trending', 'GET', ['limit' => '5']);
        $response = $this->controller->trending($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $this->decodeResponse($response));
    }

    #[Test]
    public function trendingHandlesArticleWithNullPublishedAt(): void
    {
        $this->performance->method('getCached')->willReturn(null);
        $this->performance->method('getTrendingArticles')->willReturn([
            ['article_id' => 7, 'views' => 100],
        ]);

        $article = $this->createStub(Article::class);
        $article->method('getId')->willReturn(7);
        $article->method('getTitle')->willReturn('Unpublished');
        $article->method('getSlug')->willReturn('unpublished');
        $article->method('getCategory')->willReturn(null);
        $article->method('getPublishedAt')->willReturn(null);

        $qb = $this->createQueryBuilderStub([$article]);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        $request = Request::create('/api/admin/stats/trending', 'GET');
        $response = $this->controller->trending($request);

        $data = $this->decodeResponse($response);
        $this->assertCount(1, $data);
        $this->assertNull($data[0]['published_at']);
    }

    #[Test]
    public function trendingHandlesViewsMapMissingArticleId(): void
    {
        $this->performance->method('getCached')->willReturn(null);
        // Trending data has article_id but views might be missing from map if array_column fails
        $this->performance->method('getTrendingArticles')->willReturn([
            ['article_id' => 10, 'views' => 500],
        ]);

        $article = $this->createArticleStub(10, 'Article 10');
        $qb = $this->createQueryBuilderStub([$article]);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        $request = Request::create('/api/admin/stats/trending', 'GET');
        $response = $this->controller->trending($request);

        $data = $this->decodeResponse($response);
        $this->assertSame(500, $data[0]['views_24h']);
    }

    // =============================================
    // siteStats()
    // =============================================

    #[Test]
    public function siteStatsReturnsCachedDataWhenAvailable(): void
    {
        $cachedData = [
            'realtime' => ['unique_visitors_today' => 500],
            'stats' => [],
        ];
        $this->performance->method('getCached')->willReturn($cachedData);

        $request = Request::create('/api/admin/stats/site', 'GET');
        $response = $this->controller->siteStats($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($cachedData, $this->decodeResponse($response));
    }

    #[Test]
    public function siteStatsReturnsFormattedDataWithRealtimeInfo(): void
    {
        $this->performance->method('getCached')->willReturn(null);
        $this->performance->method('getUniqueVisitorCount')->willReturn(250);

        $stats = [
            $this->createSiteStatsDailyStub('2026-03-20', 1000, 800, 200, 35.5, 240),
            $this->createSiteStatsDailyStub('2026-03-19', 900, 700, 150, 40.0, 200),
        ];
        $this->siteStatsRepository->method('findByDateRange')->willReturn($stats);

        $request = Request::create('/api/admin/stats/site', 'GET', ['range' => '7days']);
        $response = $this->controller->siteStats($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);

        $this->assertSame(250, $data['realtime']['unique_visitors_today']);
        $this->assertCount(2, $data['stats']);

        $this->assertSame('2026-03-20', $data['stats'][0]['date']);
        $this->assertSame(1000, $data['stats'][0]['total_visits']);
        $this->assertSame(800, $data['stats'][0]['unique_visitors']);
        $this->assertSame(200, $data['stats'][0]['new_visitors']);
        $this->assertSame(35.5, $data['stats'][0]['bounce_rate']);
        $this->assertSame(240, $data['stats'][0]['avg_session_duration']);
    }

    #[Test]
    public function siteStatsReturnsEmptyStatsWithRealtimeData(): void
    {
        $this->performance->method('getCached')->willReturn(null);
        $this->performance->method('getUniqueVisitorCount')->willReturn(0);
        $this->siteStatsRepository->method('findByDateRange')->willReturn([]);

        $request = Request::create('/api/admin/stats/site', 'GET', ['range' => 'today']);
        $response = $this->controller->siteStats($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertSame(0, $data['realtime']['unique_visitors_today']);
        $this->assertEmpty($data['stats']);
    }

    #[Test]
    public function siteStatsHandlesNullBounceRateAndSessionDuration(): void
    {
        $this->performance->method('getCached')->willReturn(null);
        $this->performance->method('getUniqueVisitorCount')->willReturn(10);

        $stats = [
            $this->createSiteStatsDailyStub('2026-03-20', 100, 80, 20, null, null),
        ];
        $this->siteStatsRepository->method('findByDateRange')->willReturn($stats);

        $request = Request::create('/api/admin/stats/site', 'GET');
        $response = $this->controller->siteStats($request);

        $data = $this->decodeResponse($response);
        $this->assertNull($data['stats'][0]['bounce_rate']);
        $this->assertNull($data['stats'][0]['avg_session_duration']);
    }

    #[Test]
    public function siteStatsUsesDefaultRange(): void
    {
        $this->performance->method('getCached')->willReturn(null);
        $this->performance->method('getUniqueVisitorCount')->willReturn(0);
        $this->siteStatsRepository->method('findByDateRange')->willReturn([]);

        // No range parameter
        $request = Request::create('/api/admin/stats/site', 'GET');
        $response = $this->controller->siteStats($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    // =============================================
    // realtime()
    // =============================================

    #[Test]
    public function realtimeReturnsCurrentStats(): void
    {
        $this->performance->method('getActiveSessionCount')->willReturn(42);
        $this->performance->method('getUniqueVisitorCount')->willReturn(350);
        $this->performance->method('getTrendingArticles')->willReturn([
            ['article_id' => 1, 'views' => 100],
            ['article_id' => 2, 'views' => 80],
            ['article_id' => 3, 'views' => 60],
        ]);

        $response = $this->controller->realtime();

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);

        $this->assertArrayHasKey('timestamp', $data);
        $this->assertIsInt($data['timestamp']);
        $this->assertSame(42, $data['active_sessions']);
        $this->assertSame(350, $data['unique_visitors_today']);
        $this->assertCount(3, $data['trending_now']);
    }

    #[Test]
    public function realtimeReturnsEmptyTrendingWhenNone(): void
    {
        $this->performance->method('getActiveSessionCount')->willReturn(0);
        $this->performance->method('getUniqueVisitorCount')->willReturn(0);
        $this->performance->method('getTrendingArticles')->willReturn([]);

        $response = $this->controller->realtime();

        $data = $this->decodeResponse($response);
        $this->assertSame(0, $data['active_sessions']);
        $this->assertSame(0, $data['unique_visitors_today']);
        $this->assertEmpty($data['trending_now']);
    }

    #[Test]
    public function realtimeTruncatesTrendingToFiveMax(): void
    {
        $this->performance->method('getActiveSessionCount')->willReturn(10);
        $this->performance->method('getUniqueVisitorCount')->willReturn(100);
        $this->performance->method('getTrendingArticles')->willReturn([
            ['article_id' => 1, 'views' => 100],
            ['article_id' => 2, 'views' => 90],
            ['article_id' => 3, 'views' => 80],
            ['article_id' => 4, 'views' => 70],
            ['article_id' => 5, 'views' => 60],
            ['article_id' => 6, 'views' => 50],
            ['article_id' => 7, 'views' => 40],
        ]);

        $response = $this->controller->realtime();

        $data = $this->decodeResponse($response);
        // array_slice(..., 0, 5) applied after getTrendingArticles(5)
        // but getTrendingArticles returns 7 items, then array_slice to 5
        $this->assertCount(5, $data['trending_now']);
    }

    #[Test]
    public function realtimeTimestampIsRecentUnixTimestamp(): void
    {
        $this->performance->method('getActiveSessionCount')->willReturn(0);
        $this->performance->method('getUniqueVisitorCount')->willReturn(0);
        $this->performance->method('getTrendingArticles')->willReturn([]);

        $before = time();
        $response = $this->controller->realtime();
        $after = time();

        $data = $this->decodeResponse($response);
        $this->assertGreaterThanOrEqual($before, $data['timestamp']);
        $this->assertLessThanOrEqual($after, $data['timestamp']);
    }

    // =============================================
    // categoryStats()
    // =============================================

    #[Test]
    public function categoryStatsReturnsCachedDataWhenAvailable(): void
    {
        $cachedData = [
            'total' => 5,
            'distribution' => [['name' => 'Politica', 'value' => 100]],
        ];
        $this->performance->method('getCached')->willReturn($cachedData);

        $request = Request::create('/api/admin/stats/categories', 'GET');
        $response = $this->controller->categoryStats($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($cachedData, $this->decodeResponse($response));
    }

    #[Test]
    public function categoryStatsReturnsDistributionSortedByValue(): void
    {
        $this->performance->method('getCached')->willReturn(null);

        // Article counts query returns counts per category
        $articleCountsQb = $this->createQueryBuilderStub([
            ['category_id' => 1, 'article_count' => '50'],
            ['category_id' => 2, 'article_count' => '150'],
            ['category_id' => 3, 'article_count' => '80'],
        ]);
        $this->articleRepository->method('createQueryBuilder')->willReturn($articleCountsQb);

        // Category details query
        $cat1 = $this->createCategoryStub(1, 'Sport', 'sport');
        $cat2 = $this->createCategoryStub(2, 'Politica', 'politica');
        $cat3 = $this->createCategoryStub(3, 'Economie', 'economie');

        $catQb = $this->createQueryBuilderStub([$cat1, $cat2, $cat3]);
        $this->categoryRepository->method('createQueryBuilder')->willReturn($catQb);
        $this->categoryRepository->method('count')->willReturn(10);

        $request = Request::create('/api/admin/stats/categories', 'GET');
        $request->headers->set('Accept-Language', 'ro');
        $response = $this->controller->categoryStats($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);

        $this->assertSame(10, $data['total']);
        $this->assertCount(3, $data['distribution']);

        // Should be sorted by value descending
        $this->assertSame('Politica', $data['distribution'][0]['name']);
        $this->assertSame(150, $data['distribution'][0]['value']);
        $this->assertSame('Economie', $data['distribution'][1]['name']);
        $this->assertSame(80, $data['distribution'][1]['value']);
        $this->assertSame('Sport', $data['distribution'][2]['name']);
        $this->assertSame(50, $data['distribution'][2]['value']);
    }

    #[Test]
    public function categoryStatsReturnsEmptyDistributionWhenNoArticles(): void
    {
        $this->performance->method('getCached')->willReturn(null);

        // No article counts
        $qb = $this->createQueryBuilderStub([]);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);
        $this->categoryRepository->method('count')->willReturn(5);

        $request = Request::create('/api/admin/stats/categories', 'GET');
        $response = $this->controller->categoryStats($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);
        $this->assertSame(5, $data['total']);
        $this->assertEmpty($data['distribution']);
    }

    #[Test]
    public function categoryStatsUsesDefaultLocale(): void
    {
        $this->performance->method('getCached')->willReturn(null);

        $qb = $this->createQueryBuilderStub([]);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);
        $this->categoryRepository->method('count')->willReturn(0);

        // No Accept-Language header
        $request = Request::create('/api/admin/stats/categories', 'GET');
        $response = $this->controller->categoryStats($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function categoryStatsFiltersCategoriesNotInCountsMap(): void
    {
        $this->performance->method('getCached')->willReturn(null);

        // Only category 1 has articles
        $articleCountsQb = $this->createQueryBuilderStub([
            ['category_id' => 1, 'article_count' => '25'],
        ]);
        $this->articleRepository->method('createQueryBuilder')->willReturn($articleCountsQb);

        // But DB returns both categories
        $cat1 = $this->createCategoryStub(1, 'Politica');
        $cat2 = $this->createCategoryStub(2, 'Sport');

        $catQb = $this->createQueryBuilderStub([$cat1, $cat2]);
        $this->categoryRepository->method('createQueryBuilder')->willReturn($catQb);
        $this->categoryRepository->method('count')->willReturn(2);

        $request = Request::create('/api/admin/stats/categories', 'GET');
        $response = $this->controller->categoryStats($request);

        $data = $this->decodeResponse($response);
        // Category 2 should be excluded because it has no count in the map
        $this->assertCount(1, $data['distribution']);
        $this->assertSame('Politica', $data['distribution'][0]['name']);
    }

    // =============================================
    // articleCounts()
    // =============================================

    #[Test]
    public function articleCountsReturnsCachedDataWhenAvailable(): void
    {
        $cachedData = [
            'total' => 1000,
            'published' => 800,
            'new' => 100,
            'submitted' => 100,
        ];
        $this->performance->method('getCached')->willReturn($cachedData);

        $response = $this->controller->articleCounts();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame($cachedData, $this->decodeResponse($response));
    }

    #[Test]
    public function articleCountsReturnsCorrectCountsFromEnumInstances(): void
    {
        $this->performance->method('getCached')->willReturn(null);

        // Doctrine may return enum instances
        $qb = $this->createQueryBuilderStub([
            ['status' => ArticleStatus::PUBLISHED, 'count' => 500],
            ['status' => ArticleStatus::NEW, 'count' => 100],
            ['status' => ArticleStatus::SUBMITTED, 'count' => 50],
            ['status' => ArticleStatus::ARCHIVED, 'count' => 30],
        ]);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        $response = $this->controller->articleCounts();

        $this->assertSame(200, $response->getStatusCode());
        $data = $this->decodeResponse($response);

        $this->assertSame(680, $data['total']); // 500 + 100 + 50 + 30
        $this->assertSame(500, $data['published']);
        $this->assertSame(100, $data['new']);
        $this->assertSame(50, $data['submitted']);
    }

    #[Test]
    public function articleCountsReturnsCorrectCountsFromStringValues(): void
    {
        $this->performance->method('getCached')->willReturn(null);

        // Doctrine may return string values
        $qb = $this->createQueryBuilderStub([
            ['status' => 'published', 'count' => 200],
            ['status' => 'new', 'count' => 50],
            ['status' => 'submitted', 'count' => 25],
        ]);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        $response = $this->controller->articleCounts();

        $data = $this->decodeResponse($response);
        $this->assertSame(275, $data['total']);
        $this->assertSame(200, $data['published']);
        $this->assertSame(50, $data['new']);
        $this->assertSame(25, $data['submitted']);
    }

    #[Test]
    public function articleCountsReturnsZerosWhenNoArticles(): void
    {
        $this->performance->method('getCached')->willReturn(null);

        $qb = $this->createQueryBuilderStub([]);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        $response = $this->controller->articleCounts();

        $data = $this->decodeResponse($response);
        $this->assertSame(0, $data['total']);
        $this->assertSame(0, $data['published']);
        $this->assertSame(0, $data['new']);
        $this->assertSame(0, $data['submitted']);
    }

    #[Test]
    public function articleCountsHandlesMixedStatusTypes(): void
    {
        $this->performance->method('getCached')->willReturn(null);

        // Mix of enum instances and string statuses
        $qb = $this->createQueryBuilderStub([
            ['status' => ArticleStatus::PUBLISHED, 'count' => 100],
            ['status' => 'new', 'count' => 20],
        ]);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        $response = $this->controller->articleCounts();

        $data = $this->decodeResponse($response);
        $this->assertSame(120, $data['total']);
        $this->assertSame(100, $data['published']);
        $this->assertSame(20, $data['new']);
        $this->assertSame(0, $data['submitted']);
    }

    #[Test]
    public function articleCountsIgnoresUnknownStatuses(): void
    {
        $this->performance->method('getCached')->willReturn(null);

        $qb = $this->createQueryBuilderStub([
            ['status' => 'published', 'count' => 100],
            ['status' => 'unknown_status', 'count' => 10],
        ]);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        $response = $this->controller->articleCounts();

        $data = $this->decodeResponse($response);
        // Total includes unknown status, but it doesn't map to any named count
        $this->assertSame(110, $data['total']);
        $this->assertSame(100, $data['published']);
        $this->assertSame(0, $data['new']);
        $this->assertSame(0, $data['submitted']);
    }

    #[Test]
    public function articleCountsIncludesArchivedInTotalOnly(): void
    {
        $this->performance->method('getCached')->willReturn(null);

        $qb = $this->createQueryBuilderStub([
            ['status' => ArticleStatus::PUBLISHED, 'count' => 500],
            ['status' => ArticleStatus::ARCHIVED, 'count' => 200],
        ]);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);

        $response = $this->controller->articleCounts();

        $data = $this->decodeResponse($response);
        // archived is added to total but has no dedicated key in the response
        $this->assertSame(700, $data['total']);
        $this->assertSame(500, $data['published']);
        $this->assertSame(0, $data['new']);
        $this->assertSame(0, $data['submitted']);
    }

    // =============================================
    // Response format validation
    // =============================================

    #[Test]
    public function allEndpointsReturnJsonResponses(): void
    {
        $this->performance->method('getCached')->willReturn(null);
        $this->performance->method('getActiveSessionCount')->willReturn(0);
        $this->performance->method('getUniqueVisitorCount')->willReturn(0);
        $this->performance->method('getTrendingArticles')->willReturn([]);
        $this->performance->method('getArticleViews')->willReturn(0);

        $article = $this->createArticleStub(1);
        $this->articleRepository->method('find')->willReturn($article);
        $this->articleStatsRepository->method('findByArticleAndDateRange')->willReturn([]);
        $this->siteStatsRepository->method('findByDateRange')->willReturn([]);

        $qb = $this->createQueryBuilderStub([]);
        $this->articleRepository->method('createQueryBuilder')->willReturn($qb);
        $this->categoryRepository->method('createQueryBuilder')->willReturn($qb);
        $this->categoryRepository->method('count')->willReturn(0);

        $request = Request::create('/test', 'GET');

        $responses = [
            $this->controller->articleStats(1, $request),
            $this->controller->trending($request),
            $this->controller->siteStats($request),
            $this->controller->realtime(),
            $this->controller->categoryStats($request),
            $this->controller->articleCounts(),
        ];

        foreach ($responses as $response) {
            $this->assertInstanceOf(JsonResponse::class, $response);
            $this->assertSame(200, $response->getStatusCode());
        }
    }
}
