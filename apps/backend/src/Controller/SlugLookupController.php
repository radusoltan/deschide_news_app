<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SlugLookupService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Slug Lookup API Controller.
 *
 * Provides REST API endpoints for slug lookup, validation, and redirect checking.
 * Uses Elasticsearch for fast lookups with database fallback.
 *
 * Endpoints:
 * - POST /api/slug/lookup - Find article by slug
 * - POST /api/slug/validate - Check if slug is available
 * - POST /api/slug/check-redirect - Check redirect chain
 *
 * @see docs/url-structure-APPROVED.md - API Documentation
 */
#[Route('/api/slug', name: 'api_slug_')]
class SlugLookupController extends AbstractController
{
    public function __construct(
        private SlugLookupService $slugLookupService
    ) {
    }

    /**
     * Lookup article by category and article slug.
     *
     * POST /api/slug/lookup
     *
     * Request body:
     * {
     *   "category_slug": "politica",
     *   "article_slug": "reforma-guvernului",
     *   "locale": "ro"
     * }
     *
     * Success response (200):
     * {
     *   "success": true,
     *   "data": {
     *     "article_id": 123,
     *     "title": "Reforma Guvernului",
     *     "slug": "reforma-guvernului",
     *     "category": {
     *       "id": 5,
     *       "name": "Politică",
     *       "slug": "politica"
     *     },
     *     "url": "/politica/reforma-guvernului",
     *     "found_via": "elasticsearch"
     *   }
     * }
     *
     * Redirect response (301):
     * {
     *   "success": false,
     *   "redirect": {
     *     "old_url": "/politica/reforma-veche",
     *     "new_url": "/economie/reforma-noua",
     *     "status_code": 301
     *   }
     * }
     *
     * Not found response (404):
     * {
     *   "success": false,
     *   "error": "Article not found",
     *   "requested": {
     *     "category_slug": "politica",
     *     "article_slug": "inexistent"
     *   }
     * }
     */
    #[Route('/lookup', name: 'lookup', methods: ['POST'])]
    public function lookup(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        if (!isset($data['category_slug']) || !isset($data['article_slug'])) {
            return $this->json([
                'success' => false,
                'error' => 'Missing required fields: category_slug, article_slug',
            ], Response::HTTP_BAD_REQUEST);
        }

        $categorySlug = (string) $data['category_slug'];
        $articleSlug = (string) $data['article_slug'];
        $locale = $data['locale'] ?? 'ro';

        // Perform lookup
        $result = $this->slugLookupService->findArticleBySlug($categorySlug, $articleSlug, $locale);

        // Article found
        if ($result['article']) {
            $article = $result['article'];
            $category = $article->getCategory();

            $localePrefix = $locale === 'ro' ? '' : $locale . '/';
            $url = '/' . $localePrefix . $categorySlug . '/' . $articleSlug;

            return $this->json([
                'success' => true,
                'data' => [
                    'article_id' => $article->getId(),
                    'title' => $article->getTitle(),
                    'slug' => $article->getSlug(),
                    'lead' => $article->getLead(),
                    'category' => [
                        'id' => $category?->getId(),
                        'name' => $category?->getTitle(),
                        'slug' => $category?->getSlug(),
                    ],
                    'url' => $url,
                    'found_via' => $result['found_via'],
                    'status' => $article->getStatus()->value,
                    'published_at' => $article->getPublishedAt()?->format('Y-m-d H:i:s'),
                ],
            ], Response::HTTP_OK);
        }

        // Redirect found
        if ($result['redirect']) {
            $redirect = $result['redirect'];

            return $this->json([
                'success' => false,
                'redirect' => [
                    'old_url' => $redirect->getOldUrl(),
                    'new_url' => $redirect->getNewUrl(),
                    'status_code' => $redirect->getHttpStatusCode(),
                    'type' => $redirect->getType(),
                ],
            ], Response::HTTP_MOVED_PERMANENTLY); // 301
        }

        // Not found
        return $this->json([
            'success' => false,
            'error' => 'Article not found',
            'requested' => [
                'category_slug' => $categorySlug,
                'article_slug' => $articleSlug,
                'locale' => $locale,
            ],
        ], Response::HTTP_NOT_FOUND);
    }

    /**
     * Validate if slug is available.
     *
     * POST /api/slug/validate
     *
     * Request body:
     * {
     *   "slug": "reforma-guvernului",
     *   "type": "article",
     *   "locale": "ro",
     *   "exclude_id": 123
     * }
     *
     * Response (200):
     * {
     *   "success": true,
     *   "available": true,
     *   "slug": "reforma-guvernului"
     * }
     */
    #[Route('/validate', name: 'validate', methods: ['POST'])]
    public function validate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        if (!isset($data['slug']) || !isset($data['type'])) {
            return $this->json([
                'success' => false,
                'error' => 'Missing required fields: slug, type',
            ], Response::HTTP_BAD_REQUEST);
        }

        $slug = (string) $data['slug'];
        $type = (string) $data['type'];
        $locale = $data['locale'] ?? 'ro';
        $excludeId = isset($data['exclude_id']) ? (int) $data['exclude_id'] : null;

        // Validate type
        if (!\in_array($type, ['article', 'category'], true)) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid type. Must be: article or category',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check availability
        $available = $this->slugLookupService->isSlugAvailable($slug, $type, $locale, $excludeId);

        return $this->json([
            'success' => true,
            'available' => $available,
            'slug' => $slug,
            'type' => $type,
            'locale' => $locale,
        ], Response::HTTP_OK);
    }

    /**
     * Check redirect chain for URL.
     *
     * POST /api/slug/check-redirect
     *
     * Request body:
     * {
     *   "url": "/politica/reforma-veche"
     * }
     *
     * Response with redirects (200):
     * {
     *   "success": true,
     *   "has_redirect": true,
     *   "chain": [
     *     {
     *       "from": "/politica/reforma-veche",
     *       "to": "/politica/reforma-noua",
     *       "status_code": 301,
     *       "type": "article",
     *       "hit_count": 42
     *     },
     *     {
     *       "from": "/politica/reforma-noua",
     *       "to": "/economie/reforma-finala",
     *       "status_code": 301,
     *       "type": "category",
     *       "hit_count": 5
     *     }
     *   ],
     *   "final_url": "/economie/reforma-finala",
     *   "chain_length": 2
     * }
     *
     * Response without redirects (200):
     * {
     *   "success": true,
     *   "has_redirect": false,
     *   "url": "/politica/reforma-guvernului"
     * }
     */
    #[Route('/check-redirect', name: 'check_redirect', methods: ['POST'])]
    public function checkRedirect(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        if (!isset($data['url'])) {
            return $this->json([
                'success' => false,
                'error' => 'Missing required field: url',
            ], Response::HTTP_BAD_REQUEST);
        }

        $url = (string) $data['url'];

        // Get redirect chain
        $result = $this->slugLookupService->getRedirectChain($url);

        if (empty($result['redirects'])) {
            return $this->json([
                'success' => true,
                'has_redirect' => false,
                'url' => $url,
            ], Response::HTTP_OK);
        }

        // Build chain response
        $chain = [];
        $previousUrl = $url;

        foreach ($result['redirects'] as $redirect) {
            $chain[] = [
                'from' => $previousUrl,
                'to' => $redirect->getNewUrl(),
                'status_code' => $redirect->getHttpStatusCode(),
                'type' => $redirect->getType(),
                'hit_count' => $redirect->getHitCount(),
                'created_at' => $redirect->getCreatedAt()->format('Y-m-d H:i:s'),
            ];

            $previousUrl = $redirect->getNewUrl();
        }

        return $this->json([
            'success' => true,
            'has_redirect' => true,
            'chain' => $chain,
            'final_url' => $result['final_url'],
            'chain_length' => $result['chain_length'],
            'warning' => $result['chain_length'] > 3 ? 'Long redirect chain detected. Consider updating to direct redirect.' : null,
        ], Response::HTTP_OK);
    }

    /**
     * Get list of reserved slugs.
     *
     * GET /api/slug/reserved
     *
     * Response (200):
     * {
     *   "success": true,
     *   "reserved_slugs": ["all", "search", "trending", ...],
     *   "count": 17
     * }
     */
    #[Route('/reserved', name: 'reserved', methods: ['GET'])]
    public function getReservedSlugs(): JsonResponse
    {
        $reservedSlugs = $this->slugLookupService->getReservedSlugs();

        return $this->json([
            'success' => true,
            'reserved_slugs' => $reservedSlugs,
            'count' => \count($reservedSlugs),
        ], Response::HTTP_OK);
    }

    /**
     * Check if a slug is reserved.
     *
     * POST /api/slug/check-reserved
     *
     * Request body:
     * {
     *   "slug": "admin"
     * }
     *
     * Response (200):
     * {
     *   "success": true,
     *   "slug": "admin",
     *   "is_reserved": true
     * }
     */
    #[Route('/check-reserved', name: 'check_reserved', methods: ['POST'])]
    public function checkReserved(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['slug'])) {
            return $this->json([
                'success' => false,
                'error' => 'Missing required field: slug',
            ], Response::HTTP_BAD_REQUEST);
        }

        $slug = (string) $data['slug'];
        $isReserved = $this->slugLookupService->isSlugReserved($slug);

        return $this->json([
            'success' => true,
            'slug' => $slug,
            'is_reserved' => $isReserved,
        ], Response::HTTP_OK);
    }

    /**
     * Bulk validate multiple slugs.
     *
     * POST /api/slug/bulk-validate
     *
     * Request body:
     * {
     *   "slugs": ["politica", "economie", "admin", "sport"],
     *   "type": "category",
     *   "locale": "ro"
     * }
     *
     * Response (200):
     * {
     *   "success": true,
     *   "results": {
     *     "politica": {
     *       "slug": "politica",
     *       "available": false,
     *       "reserved": false,
     *       "valid": false
     *     },
     *     "economie": {
     *       "slug": "economie",
     *       "available": true,
     *       "reserved": false,
     *       "valid": true
     *     },
     *     "admin": {
     *       "slug": "admin",
     *       "available": true,
     *       "reserved": true,
     *       "valid": false
     *     }
     *   },
     *   "summary": {
     *     "total": 4,
     *     "valid": 1,
     *     "invalid": 3,
     *     "reserved": 1
     *   }
     * }
     */
    #[Route('/bulk-validate', name: 'bulk_validate', methods: ['POST'])]
    public function bulkValidate(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['slugs']) || !\is_array($data['slugs']) || !isset($data['type'])) {
            return $this->json([
                'success' => false,
                'error' => 'Missing required fields: slugs (array), type',
            ], Response::HTTP_BAD_REQUEST);
        }

        $slugs = $data['slugs'];
        $type = (string) $data['type'];
        $locale = $data['locale'] ?? 'ro';

        // Limit to 50 slugs per request
        if (\count($slugs) > 50) {
            return $this->json([
                'success' => false,
                'error' => 'Maximum 50 slugs per request',
            ], Response::HTTP_BAD_REQUEST);
        }

        $results = $this->slugLookupService->bulkValidate($slugs, $type, $locale);

        // Calculate summary
        $summary = [
            'total' => \count($results),
            'valid' => \count(array_filter($results, fn ($r) => $r['valid'])),
            'invalid' => \count(array_filter($results, fn ($r) => !$r['valid'])),
            'reserved' => \count(array_filter($results, fn ($r) => $r['reserved'])),
        ];

        return $this->json([
            'success' => true,
            'results' => $results,
            'summary' => $summary,
        ], Response::HTTP_OK);
    }

    /**
     * Generate slug suggestions from title.
     *
     * POST /api/slug/suggest
     *
     * Request body:
     * {
     *   "title": "Reforma Sistemului de Sănătate",
     *   "type": "article",
     *   "locale": "ro",
     *   "max_suggestions": 5
     * }
     *
     * Response (200):
     * {
     *   "success": true,
     *   "title": "Reforma Sistemului de Sănătate",
     *   "suggestions": [
     *     {
     *       "slug": "reforma-sistemului-de-sanatate",
     *       "available": true,
     *       "reserved": false
     *     },
     *     {
     *       "slug": "reforma-sistemului-de-sanatate-1",
     *       "available": true,
     *       "reserved": false
     *     },
     *     ...
     *   ],
     *   "count": 5
     * }
     */
    #[Route('/suggest', name: 'suggest', methods: ['POST'])]
    public function suggest(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['title']) || !isset($data['type'])) {
            return $this->json([
                'success' => false,
                'error' => 'Missing required fields: title, type',
            ], Response::HTTP_BAD_REQUEST);
        }

        $title = (string) $data['title'];
        $type = (string) $data['type'];
        $locale = $data['locale'] ?? 'ro';
        $maxSuggestions = isset($data['max_suggestions']) ? min((int) $data['max_suggestions'], 10) : 5;

        $suggestions = $this->slugLookupService->generateSlugSuggestions($title, $type, $locale, $maxSuggestions);

        return $this->json([
            'success' => true,
            'title' => $title,
            'suggestions' => $suggestions,
            'count' => \count($suggestions),
        ], Response::HTTP_OK);
    }
}
