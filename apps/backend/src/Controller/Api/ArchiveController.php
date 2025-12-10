<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\ArticleArchiveService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Archive Navigation API Controller.
 *
 * Provides supplementary endpoints for archive navigation and statistics.
 * Works alongside the /api/archived_articles endpoint (ArchivedArticleProvider)
 * to offer comprehensive archive browsing capabilities.
 *
 * Features:
 * - Available years with article counts
 * - Archive statistics (total, by year, by category)
 * - Categories with archived content
 * - Aggressive caching (24h browser, 7d CDN)
 *
 * Cache Strategy:
 * - Browser cache: 24 hours (86400s)
 * - CDN/proxy cache: 7 days (604800s)
 * - Rationale: Archive data changes infrequently, safe to cache aggressively
 */
#[Route('/api/archive', name: 'api_archive_')]
class ArchiveController extends AbstractController
{
    public function __construct(
        private readonly ArticleArchiveService $archiveService,
    ) {
    }

    /**
     * Get available years with article counts.
     *
     * Returns an array of years that have archived articles, sorted descending.
     * Each year includes the count of archived articles from that year.
     *
     * Response Format:
     * [
     *   {"year": 2023, "count": 1547},
     *   {"year": 2022, "count": 2103},
     *   {"year": 2021, "count": 1892}
     * ]
     *
     * @param Request $request HTTP request (for locale)
     *
     * @return JsonResponse Array of year objects with counts
     *
     * Example: GET /api/archive/years
     * Example: GET /api/archive/years?locale=en
     */
    #[Route('/years', name: 'years', methods: ['GET'])]
    public function getYears(Request $request): JsonResponse
    {
        // Get locale from query parameter or Accept-Language header
        $locale = $request->query->get('locale')
            ?? $request->headers->get('Accept-Language')
            ?? 'ro';

        // Extract just the language code (e.g., 'en' from 'en-US')
        if (str_contains($locale, '-')) {
            $locale = explode('-', $locale)[0];
        }
        if (str_contains($locale, ',')) {
            $locale = explode(',', $locale)[0];
        }

        // Get years data from service
        $years = $this->archiveService->getAvailableYears($locale);

        // Create response with cache headers
        $response = new JsonResponse($years);

        // Cache-Control: public (cacheable by CDN/proxy), max-age=86400 (24h browser), s-maxage=604800 (7d CDN)
        $response->headers->set('Cache-Control', 'public, max-age=86400, s-maxage=604800');

        // Add Content-Type explicitly (though JsonResponse sets it by default)
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * Get comprehensive archive statistics.
     *
     * Returns aggregated statistics about archived content:
     * - Total count of all archived articles
     * - Breakdown by year (same as /years endpoint)
     * - Breakdown by category with counts
     *
     * Response Format:
     * {
     *   "total": 5542,
     *   "byYear": [
     *     {"year": 2023, "count": 1547},
     *     {"year": 2022, "count": 2103}
     *   ],
     *   "byCategory": [
     *     {"id": 5, "name": "Politică", "count": 1234},
     *     {"id": 12, "name": "Economie", "count": 987}
     *   ]
     * }
     *
     * @param Request $request HTTP request (for locale)
     *
     * @return JsonResponse Statistics object
     *
     * Example: GET /api/archive/stats
     * Example: GET /api/archive/stats?locale=ru
     */
    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function getStats(Request $request): JsonResponse
    {
        // Get locale from query parameter or Accept-Language header
        $locale = $request->query->get('locale')
            ?? $request->headers->get('Accept-Language')
            ?? 'ro';

        // Extract just the language code
        if (str_contains($locale, '-')) {
            $locale = explode('-', $locale)[0];
        }
        if (str_contains($locale, ',')) {
            $locale = explode(',', $locale)[0];
        }

        // Get statistics from service
        $stats = $this->archiveService->getArchiveStats($locale);

        // Create response with cache headers
        $response = new JsonResponse($stats);

        // Same aggressive caching as years endpoint
        $response->headers->set('Cache-Control', 'public, max-age=86400, s-maxage=604800');
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }

    /**
     * Get categories that have archived articles.
     *
     * Returns only categories that contain at least one archived article,
     * with the count of archived articles per category.
     * Useful for building archive navigation filters.
     *
     * Response Format:
     * [
     *   {
     *     "id": 5,
     *     "name": "Politică",
     *     "slug": "politica",
     *     "count": 1234
     *   },
     *   {
     *     "id": 12,
     *     "name": "Economie",
     *     "slug": "economie",
     *     "count": 987
     *   }
     * ]
     *
     * @param Request $request HTTP request (for locale)
     *
     * @return JsonResponse Array of category objects with counts
     *
     * Example: GET /api/archive/categories
     * Example: GET /api/archive/categories?locale=en
     */
    #[Route('/categories', name: 'categories', methods: ['GET'])]
    public function getCategories(Request $request): JsonResponse
    {
        // Get locale from query parameter or Accept-Language header
        $locale = $request->query->get('locale')
            ?? $request->headers->get('Accept-Language')
            ?? 'ro';

        // Extract just the language code
        if (str_contains($locale, '-')) {
            $locale = explode('-', $locale)[0];
        }
        if (str_contains($locale, ',')) {
            $locale = explode(',', $locale)[0];
        }

        // Get categories with archived article counts from service
        $categories = $this->archiveService->getCategoriesWithArchivedArticles($locale);

        // Create response with cache headers
        $response = new JsonResponse($categories);

        // Same aggressive caching as other archive endpoints
        $response->headers->set('Cache-Control', 'public, max-age=86400, s-maxage=604800');
        $response->headers->set('Content-Type', 'application/json');

        return $response;
    }
}
