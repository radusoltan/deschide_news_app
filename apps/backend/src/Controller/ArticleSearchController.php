<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\ElasticService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('', name: 'api_')]
class ArticleSearchController extends AbstractController
{
    public function __construct(
        private readonly ElasticService $elasticService
    ) {
    }

    /**
     * Test endpoint to verify controller is working.
     */
    #[Route('/search-test', name: 'search_test', methods: ['GET'])]
    public function searchTest(): JsonResponse
    {
        return $this->json(['status' => 'ok', 'message' => 'Controller is working']);
    }

    /**
     * Search articles using Elasticsearch.
     *
     * Query parameters:
     * - q: Search query (required, min 2 chars)
     * - page: Page number (default: 1)
     * - itemsPerPage: Items per page (default: 12, max: 100)
     * - locale: Language code (ro, en, ru) - defaults to Accept-Language header or query param
     * - categoryId: Filter by category ID (optional)
     * - status: Filter by status (default: published)
     *
     * @example GET /search?q=economia&page=1&itemsPerPage=12&locale=ro
     */
    #[Route('/search', name: 'search', methods: ['GET'], priority: 100)]
    public function search(Request $request): JsonResponse
    {
        // Extract search query
        $query = trim((string) $request->query->get('q', ''));

        if (strlen($query) < 2) {
            return $this->json([
                'results' => [],
                'total' => 0,
                'page' => 1,
                'itemsPerPage' => 0,
                'totalPages' => 0,
                'query' => $query,
                'error' => 'Search query must be at least 2 characters',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Extract pagination
        $page = max(1, (int) $request->query->get('page', 1));
        $itemsPerPage = min(100, max(1, (int) $request->query->get('itemsPerPage', 12)));
        $from = ($page - 1) * $itemsPerPage;

        // Extract locale (from query param or Accept-Language header)
        $locale = $request->query->get('locale')
            ?: $this->extractLocale($request);

        // Build filters
        $filters = [
            'status' => 'published', // Only published articles
        ];

        if ($categoryId = $request->query->get('categoryId')) {
            $filters['category_id'] = (int) $categoryId;
        }

        // Perform search
        try {
            $searchResults = $this->elasticService->search(
                query: $query,
                from: $from,
                size: $itemsPerPage,
                filters: $filters,
                sort: [], // Use default relevance sorting from ElasticService
                locale: $locale
            );

            $hits = $searchResults['hits']['hits'] ?? [];
            $total = $searchResults['hits']['total']['value'] ?? 0;
            $totalPages = (int) ceil($total / $itemsPerPage);

            // Transform results to match frontend expectations
            $results = array_map(function ($hit) use ($locale) {
                $source = $hit['_source'];
                return [
                    'id' => $source['id'],
                    'title' => $source['title'],
                    'slug' => $source['slug'],
                    'excerpt' => $source['lead'] ?? '',
                    'category' => [
                        'slug' => $source['category']['slug'] ?? '',
                        'title' => $source['category']['name'] ?? '',
                    ],
                    'publishedAt' => $source['published_at'] ?? $source['created_at'],
                    'articleImages' => $this->transformImages($source),
                    // Include highlights if available
                    'highlights' => $hit['highlight'] ?? null,
                ];
            }, $hits);

            return $this->json([
                'results' => $results,
                'total' => $total,
                'page' => $page,
                'itemsPerPage' => $itemsPerPage,
                'totalPages' => $totalPages,
                'query' => $query,
            ], Response::HTTP_OK, [
                'Cache-Control' => 'public, max-age=60', // Cache for 1 minute
                'Vary' => 'Accept-Language',
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'results' => [],
                'total' => 0,
                'page' => $page,
                'itemsPerPage' => $itemsPerPage,
                'totalPages' => 0,
                'query' => $query,
                'error' => 'Search service temporarily unavailable',
                'debug' => $this->getParameter('kernel.debug') ? $e->getMessage() : null,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Transform image data from Elasticsearch source.
     */
    private function transformImages(array $source): array
    {
        // Note: Images are not stored in Elasticsearch index currently
        // This would need to be added to the indexing process
        // For now, return empty array - frontend will handle gracefully
        return [];
    }

    /**
     * Extract locale from request (Accept-Language header).
     */
    private function extractLocale(Request $request): string
    {
        $acceptLanguage = $request->headers->get('Accept-Language', 'ro');
        $locale = substr($acceptLanguage, 0, 2);

        return in_array($locale, ['ro', 'en', 'ru'], true) ? $locale : 'ro';
    }
}
