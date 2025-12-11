<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use App\Repository\ArticleStatsDailyRepository;
use App\Repository\CategoryRepository;
use App\Repository\SiteStatsDailyRepository;
use App\Service\PerformanceService;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/stats', name: 'api_admin_stats_')]
class StatsController extends AbstractController
{
    public function __construct(
        private readonly PerformanceService $performance,
        private readonly ArticleStatsDailyRepository $articleStatsRepository,
        private readonly SiteStatsDailyRepository $siteStatsRepository,
        private readonly ArticleRepository $articleRepository,
        private readonly CategoryRepository $categoryRepository
    ) {
    }

    #[Route('/article/{id}', name: 'article', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function articleStats(int $id, Request $request): JsonResponse
    {
        $dateRange = $request->query->get('range', '7days');
        [$startDate, $endDate] = $this->parseDateRange($dateRange);

        // Check cache first
        $cacheKey = "api:stats:article:{$id}:{$dateRange}";
        $cached = $this->performance->getCached($cacheKey);
        if ($cached !== null) {
            return new JsonResponse($cached);
        }

        $article = $this->articleRepository->find($id);
        if (!$article) {
            return new JsonResponse(['error' => 'Article not found'], 404);
        }

        // Get stats from daily aggregation
        $stats = $this->articleStatsRepository->findByArticleAndDateRange($id, $startDate, $endDate);

        // Get current views from Redis
        $currentViews = $this->performance->getArticleViews($id);

        $data = [
            'article_id' => $id,
            'title' => $article->getTitle(),
            'current_views' => $currentViews,
            'stats' => array_map(fn ($stat) => [
                'date' => $stat->getDate()->format('Y-m-d'),
                'views' => $stat->getViews(),
                'unique_visitors' => $stat->getUniqueVisitors(),
                'avg_reading_time' => $stat->getAvgReadingTime(),
                'completion_rate' => $stat->getCompletionRate(),
            ], $stats),
        ];

        // Cache for 60 seconds
        $this->performance->setCached($cacheKey, $data, 60);

        return new JsonResponse($data);
    }

    #[Route('/trending', name: 'trending', methods: ['GET'])]
    // Public endpoint for frontend trending section
    public function trending(Request $request): JsonResponse
    {
        $limit = (int) $request->query->get('limit', 10);
        $locale = $request->headers->get('Accept-Language', 'ro');

        // Check cache first
        $cacheKey = "api:stats:trending:{$limit}:{$locale}";
        $cached = $this->performance->getCached($cacheKey);
        if ($cached !== null) {
            return new JsonResponse($cached);
        }

        // Get trending from Redis
        $trending = $this->performance->getTrendingArticles($limit);

        if (empty($trending)) {
            $this->performance->setCached($cacheKey, [], 60);

            return new JsonResponse([]);
        }

        // Extract article IDs
        $articleIds = array_column($trending, 'article_id');
        $viewsMap = array_column($trending, 'views', 'article_id');

        // OPTIMIZATION: Fetch all articles in a single query with eager loading
        $qb = $this->articleRepository->createQueryBuilder('a');
        $qb->leftJoin('a.category', 'c')
            ->addSelect('c')
            ->where($qb->expr()->in('a.id', ':ids'))
            ->setParameter('ids', $articleIds)
            ->setHint(
                \Gedmo\Translatable\Query\TreeWalker\TranslationWalker::HINT_TRANSLATABLE_LOCALE,
                $locale
            )
            ->setHint(
                \Gedmo\Translatable\Query\TreeWalker\TranslationWalker::HINT_INNER_JOIN,
                false
            );

        $articlesResult = $qb->getQuery()->getResult();

        // Create a map for quick lookup
        $articlesMap = [];
        foreach ($articlesResult as $article) {
            $articlesMap[$article->getId()] = $article;
        }

        // Build response in the order of trending (preserve Redis order)
        $articles = [];
        foreach ($trending as $item) {
            $articleId = $item['article_id'];
            if (isset($articlesMap[$articleId])) {
                $article = $articlesMap[$articleId];
                $category = $article->getCategory();
                $categoryData = null;

                if ($category) {
                    $categoryData = [
                        'id' => $category->getId(),
                        'name' => $category->getTitle(),
                        'slug' => $category->getSlug(),
                    ];
                }

                $articles[] = [
                    'id' => $article->getId(),
                    'title' => $article->getTitle(),
                    'slug' => $article->getSlug(),
                    'category' => $categoryData,
                    'views_24h' => $viewsMap[$articleId] ?? 0,
                    'published_at' => $article->getPublishedAt()?->format('c'),
                ];
            }
        }

        // Cache for 60 seconds
        $this->performance->setCached($cacheKey, $articles, 60);

        return new JsonResponse($articles);
    }

    #[Route('/site', name: 'site', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function siteStats(Request $request): JsonResponse
    {
        $dateRange = $request->query->get('range', '7days');
        [$startDate, $endDate] = $this->parseDateRange($dateRange);

        // Check cache first
        $cacheKey = "api:stats:site:{$dateRange}";
        $cached = $this->performance->getCached($cacheKey);
        if ($cached !== null) {
            return new JsonResponse($cached);
        }

        // Get daily stats
        $stats = $this->siteStatsRepository->findByDateRange($startDate, $endDate);

        // Get today's real-time stats
        $today = date('Y-m-d');
        $todayUniqueVisitors = $this->performance->getUniqueVisitorCount($today);

        $data = [
            'realtime' => [
                'unique_visitors_today' => $todayUniqueVisitors,
            ],
            'stats' => array_map(fn ($stat) => [
                'date' => $stat->getDate()->format('Y-m-d'),
                'total_visits' => $stat->getTotalVisits(),
                'unique_visitors' => $stat->getUniqueVisitors(),
                'new_visitors' => $stat->getNewVisitors(),
                'bounce_rate' => $stat->getBounceRate(),
                'avg_session_duration' => $stat->getAvgSessionDuration(),
            ], $stats),
        ];

        // Cache for 60 seconds
        $this->performance->setCached($cacheKey, $data, 60);

        return new JsonResponse($data);
    }

    #[Route('/realtime', name: 'realtime', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function realtime(): JsonResponse
    {
        $today = date('Y-m-d');

        $data = [
            'timestamp' => time(),
            'active_sessions' => $this->performance->getActiveSessionCount(),
            'unique_visitors_today' => $this->performance->getUniqueVisitorCount($today),
            'trending_now' => \array_slice($this->performance->getTrendingArticles(5), 0, 5),
        ];

        return new JsonResponse($data);
    }

    #[Route('/categories', name: 'categories', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function categoryStats(Request $request): JsonResponse
    {
        $locale = $request->headers->get('Accept-Language', 'ro');

        // Check cache first
        $cacheKey = "api:stats:categories:{$locale}";
        $cached = $this->performance->getCached($cacheKey);
        if ($cached !== null) {
            return new JsonResponse($cached);
        }

        // OPTIMIZATION: Get article counts grouped by category in a single query
        $qb = $this->articleRepository->createQueryBuilder('a');
        $qb->select('IDENTITY(a.category) as category_id, COUNT(a.id) as article_count')
            ->where('a.status = :status')
            ->andWhere('a.category IS NOT NULL')
            ->setParameter('status', ArticleStatus::PUBLISHED->value)
            ->groupBy('a.category')
            ->orderBy('article_count', 'DESC');

        $articleCounts = $qb->getQuery()->getResult();

        // Create a map of category ID => count
        $countsMap = [];
        foreach ($articleCounts as $row) {
            $countsMap[$row['category_id']] = (int) $row['article_count'];
        }

        // Get category details with locale support
        if (!empty($countsMap)) {
            $categoryIds = array_keys($countsMap);
            $qb = $this->categoryRepository->createQueryBuilder('c');
            $qb->where($qb->expr()->in('c.id', ':ids'))
                ->setParameter('ids', $categoryIds)
                ->setHint(
                    \Gedmo\Translatable\Query\TreeWalker\TranslationWalker::HINT_TRANSLATABLE_LOCALE,
                    $locale
                )
                ->setHint(
                    \Gedmo\Translatable\Query\TreeWalker\TranslationWalker::HINT_INNER_JOIN,
                    false
                );

            $categories = $qb->getQuery()->getResult();

            $distribution = [];
            foreach ($categories as $category) {
                $categoryId = $category->getId();
                if (isset($countsMap[$categoryId])) {
                    $distribution[] = [
                        'name' => $category->getTitle(),
                        'value' => $countsMap[$categoryId],
                    ];
                }
            }

            // Sort by value descending
            usort($distribution, fn ($a, $b) => $b['value'] <=> $a['value']);
        } else {
            $distribution = [];
        }

        // Get total categories count
        $totalCategories = $this->categoryRepository->count([]);

        $data = [
            'total' => $totalCategories,
            'distribution' => $distribution,
        ];

        // Cache for 5 minutes
        $this->performance->setCached($cacheKey, $data, 300);

        return new JsonResponse($data);
    }

    #[Route('/article-counts', name: 'article_counts', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function articleCounts(): JsonResponse
    {
        // Check cache first
        $cacheKey = 'api:stats:article-counts';
        $cached = $this->performance->getCached($cacheKey);
        if ($cached !== null) {
            return new JsonResponse($cached);
        }

        // OPTIMIZATION: Use single query with GROUP BY instead of 4 separate COUNT queries
        $qb = $this->articleRepository->createQueryBuilder('a');
        $qb->select('a.status, COUNT(a.id) as count')
            ->groupBy('a.status');

        $results = $qb->getQuery()->getResult();

        // Initialize counts
        $counts = [
            'total' => 0,
            'published' => 0,
            'new' => 0,
            'submitted' => 0,
        ];

        // Map results to counts
        foreach ($results as $row) {
            $status = $row['status'];
            $count = (int) $row['count'];
            $counts['total'] += $count;

            if ($status === ArticleStatus::PUBLISHED->value) {
                $counts['published'] = $count;
            } elseif ($status === ArticleStatus::NEW->value) {
                $counts['new'] = $count;
            } elseif ($status === ArticleStatus::SUBMITTED->value) {
                $counts['submitted'] = $count;
            }
        }

        $data = $counts;

        // Cache for 60 seconds
        $this->performance->setCached($cacheKey, $data, 60);

        return new JsonResponse($data);
    }

    private function parseDateRange(string $range): array
    {
        $endDate = new DateTime();

        $startDate = match ($range) {
            'today' => new DateTime('today'),
            'yesterday' => new DateTime('yesterday'),
            '7days' => new DateTime('-7 days'),
            '30days' => new DateTime('-30 days'),
            default => new DateTime('-7 days'),
        };

        return [$startDate, $endDate];
    }
}
