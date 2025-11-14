<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UrlRedirectRepository;
use App\Service\SlugLookupService;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Redirect Management API Controller.
 *
 * Provides REST API endpoints for redirect management, statistics,
 * and health checking.
 *
 * Endpoints:
 * - GET /api/redirects/statistics - System-wide redirect statistics
 * - GET /api/redirects/by-entity - Find redirects by entity
 * - GET /api/redirects/health - Health check for redirect system
 * - POST /api/redirects/find-chains - Find problematic redirect chains
 *
 * @see docs/url-structure-APPROVED.md - Redirect Management
 */
#[Route('/api/redirects', name: 'api_redirects_')]
class RedirectManagementController extends AbstractController
{
    public function __construct(
        private UrlRedirectRepository $redirectRepository,
        private SlugLookupService $slugLookupService
    ) {
    }

    /**
     * Get redirect statistics.
     *
     * GET /api/redirects/statistics
     *
     * Response (200):
     * {
     *   "success": true,
     *   "statistics": {
     *     "total": 1523,
     *     "by_type": {
     *       "article": 1205,
     *       "category": 318
     *     },
     *     "unused": 45,
     *     "most_used": [
     *       {
     *         "id": 123,
     *         "oldUrl": "/old-category/article",
     *         "newUrl": "/new-category/article",
     *         "hitCount": 1542
     *       },
     *       ...
     *     ]
     *   }
     * }
     */
    #[Route('/statistics', name: 'statistics', methods: ['GET'])]
    public function getStatistics(): JsonResponse
    {
        $statistics = $this->redirectRepository->getStatistics();

        return $this->json([
            'success' => true,
            'statistics' => $statistics,
            'timestamp' => date('Y-m-d H:i:s'),
        ], Response::HTTP_OK);
    }

    /**
     * Find redirects by entity.
     *
     * GET /api/redirects/by-entity?type=article&entity_id=123
     *
     * Response (200):
     * {
     *   "success": true,
     *   "entity": {
     *     "type": "article",
     *     "id": 123
     *   },
     *   "redirects": [
     *     {
     *       "id": 45,
     *       "old_url": "/politica/vechiul-slug",
     *       "new_url": "/economie/noul-slug",
     *       "locale": "ro",
     *       "status_code": 301,
     *       "hit_count": 42,
     *       "created_at": "2025-10-25 10:30:00",
     *       "last_accessed_at": "2025-10-31 14:20:15"
     *     },
     *     ...
     *   ],
     *   "count": 3
     * }
     */
    #[Route('/by-entity', name: 'by_entity', methods: ['GET'])]
    public function getByEntity(Request $request): JsonResponse
    {
        $type = $request->query->get('type');
        $entityId = $request->query->get('entity_id');

        if (!$type || !$entityId) {
            return $this->json([
                'success' => false,
                'error' => 'Missing required parameters: type, entity_id',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!\in_array($type, ['article', 'category', 'author', 'manual'], true)) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid type. Must be: article, category, author, or manual',
            ], Response::HTTP_BAD_REQUEST);
        }

        $redirects = $this->redirectRepository->findByEntity($type, (int) $entityId);

        $redirectsData = array_map(function ($redirect) {
            return [
                'id' => $redirect->getId(),
                'old_url' => $redirect->getOldUrl(),
                'new_url' => $redirect->getNewUrl(),
                'locale' => $redirect->getLocale(),
                'status_code' => $redirect->getHttpStatusCode(),
                'hit_count' => $redirect->getHitCount(),
                'created_at' => $redirect->getCreatedAt()->format('Y-m-d H:i:s'),
                'last_accessed_at' => $redirect->getLastAccessedAt()?->format('Y-m-d H:i:s'),
            ];
        }, $redirects);

        return $this->json([
            'success' => true,
            'entity' => [
                'type' => $type,
                'id' => (int) $entityId,
            ],
            'redirects' => $redirectsData,
            'count' => \count($redirectsData),
        ], Response::HTTP_OK);
    }

    /**
     * Health check for redirect system.
     *
     * GET /api/redirects/health
     *
     * Response (200):
     * {
     *   "success": true,
     *   "health": {
     *     "status": "healthy",
     *     "total_redirects": 1523,
     *     "unused_redirects": 45,
     *     "unused_percentage": 2.95,
     *     "old_redirects": 12,
     *     "long_chains": 3,
     *     "issues": [
     *       {
     *         "type": "long_chain",
     *         "severity": "warning",
     *         "description": "Found 3 redirect chains with more than 3 hops",
     *         "recommendation": "Consider consolidating these chains"
     *       }
     *     ]
     *   }
     * }
     */
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function getHealth(): JsonResponse
    {
        $statistics = $this->redirectRepository->getStatistics();
        $total = $statistics['total'];
        $unused = $statistics['unused'];

        // Check for old redirects (older than 6 months with 0 hits)
        $sixMonthsAgo = new DateTimeImmutable('-6 months');
        $oldRedirects = $this->redirectRepository->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.createdAt < :date')
            ->andWhere('r.hitCount = 0')
            ->setParameter('date', $sixMonthsAgo)
            ->getQuery()
            ->getSingleScalarResult();

        // Check for long redirect chains (sample check)
        $allRedirects = $this->redirectRepository->findAll();
        $longChains = 0;
        $checkedUrls = [];

        foreach ($allRedirects as $redirect) {
            $oldUrl = $redirect->getOldUrl();
            if (isset($checkedUrls[$oldUrl])) {
                continue;
            }

            $chain = $this->slugLookupService->getRedirectChain($oldUrl);
            if ($chain['chain_length'] > 3) {
                ++$longChains;
            }
            $checkedUrls[$oldUrl] = true;

            // Limit check to prevent timeout
            if (\count($checkedUrls) > 100) {
                break;
            }
        }

        // Determine overall health status
        $issues = [];
        $status = 'healthy';

        if ($unused > 0) {
            $unusedPercentage = ($unused / max($total, 1)) * 100;
            if ($unusedPercentage > 10) {
                $status = 'warning';
                $issues[] = [
                    'type' => 'unused_redirects',
                    'severity' => 'warning',
                    'count' => $unused,
                    'percentage' => round($unusedPercentage, 2),
                    'description' => \sprintf('Found %d unused redirects (%.2f%%)', $unused, $unusedPercentage),
                    'recommendation' => 'Consider removing redirects that have never been accessed',
                ];
            }
        }

        if ($oldRedirects > 0) {
            $issues[] = [
                'type' => 'old_redirects',
                'severity' => 'info',
                'count' => (int) $oldRedirects,
                'description' => \sprintf('Found %d redirects older than 6 months with no hits', $oldRedirects),
                'recommendation' => 'Review and potentially clean up old unused redirects',
            ];
        }

        if ($longChains > 0) {
            $status = 'warning';
            $issues[] = [
                'type' => 'long_chains',
                'severity' => 'warning',
                'count' => $longChains,
                'description' => \sprintf('Found %d redirect chains with more than 3 hops', $longChains),
                'recommendation' => 'Consolidate long chains to improve performance',
            ];
        }

        if (empty($issues)) {
            $issues[] = [
                'type' => 'none',
                'severity' => 'info',
                'description' => 'No issues detected. Redirect system is healthy.',
            ];
        }

        return $this->json([
            'success' => true,
            'health' => [
                'status' => $status,
                'total_redirects' => $total,
                'unused_redirects' => $unused,
                'unused_percentage' => $total > 0 ? round(($unused / $total) * 100, 2) : 0,
                'old_redirects' => (int) $oldRedirects,
                'long_chains' => $longChains,
                'issues' => $issues,
            ],
            'timestamp' => date('Y-m-d H:i:s'),
        ], Response::HTTP_OK);
    }

    /**
     * Find problematic redirect chains.
     *
     * POST /api/redirects/find-chains
     *
     * Request body:
     * {
     *   "min_chain_length": 3,
     *   "limit": 20
     * }
     *
     * Response (200):
     * {
     *   "success": true,
     *   "chains": [
     *     {
     *       "start_url": "/old1/article",
     *       "final_url": "/final/article",
     *       "chain_length": 4,
     *       "total_hits": 152,
     *       "chain": [
     *         {
     *           "from": "/old1/article",
     *           "to": "/old2/article",
     *           "hit_count": 42
     *         },
     *         {
     *           "from": "/old2/article",
     *           "to": "/old3/article",
     *           "hit_count": 58
     *         },
     *         ...
     *       ]
     *     },
     *     ...
     *   ],
     *   "count": 5,
     *   "summary": {
     *     "total_checked": 100,
     *     "problematic_chains": 5,
     *     "avg_chain_length": 3.4
     *   }
     * }
     */
    #[Route('/find-chains', name: 'find_chains', methods: ['POST'])]
    public function findChains(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $minChainLength = $data['min_chain_length'] ?? 3;
        $limit = min($data['limit'] ?? 20, 100); // Max 100 to prevent timeout

        // Get all redirects
        $allRedirects = $this->redirectRepository->findAll();
        $problematicChains = [];
        $checkedUrls = [];
        $totalChecked = 0;

        foreach ($allRedirects as $redirect) {
            if ($totalChecked >= $limit) {
                break;
            }

            $oldUrl = $redirect->getOldUrl();
            if (isset($checkedUrls[$oldUrl])) {
                continue;
            }

            ++$totalChecked;
            $chainResult = $this->slugLookupService->getRedirectChain($oldUrl);

            if ($chainResult['chain_length'] >= $minChainLength) {
                $totalHits = array_sum(array_map(fn ($r) => $r->getHitCount(), $chainResult['redirects']));

                $chain = [];
                $previousUrl = $oldUrl;
                foreach ($chainResult['redirects'] as $chainRedirect) {
                    $chain[] = [
                        'from' => $previousUrl,
                        'to' => $chainRedirect->getNewUrl(),
                        'hit_count' => $chainRedirect->getHitCount(),
                        'status_code' => $chainRedirect->getHttpStatusCode(),
                    ];
                    $previousUrl = $chainRedirect->getNewUrl();
                }

                $problematicChains[] = [
                    'start_url' => $oldUrl,
                    'final_url' => $chainResult['final_url'],
                    'chain_length' => $chainResult['chain_length'],
                    'total_hits' => $totalHits,
                    'chain' => $chain,
                ];
            }

            $checkedUrls[$oldUrl] = true;
        }

        // Calculate summary
        $avgChainLength = 0;
        if (\count($problematicChains) > 0) {
            $avgChainLength = array_sum(array_column($problematicChains, 'chain_length')) / \count($problematicChains);
        }

        return $this->json([
            'success' => true,
            'chains' => $problematicChains,
            'count' => \count($problematicChains),
            'summary' => [
                'total_checked' => $totalChecked,
                'problematic_chains' => \count($problematicChains),
                'avg_chain_length' => round($avgChainLength, 2),
                'min_chain_length' => $minChainLength,
            ],
        ], Response::HTTP_OK);
    }

    /**
     * Lookup redirect for a URL.
     *
     * GET /api/redirects/lookup?url=/old-path&locale=en
     *
     * Checks if a redirect exists for the given URL and locale.
     * Used by frontend middleware to handle 301 redirects.
     *
     * Response (200 - redirect found):
     * {
     *   "success": true,
     *   "redirect": {
     *     "id": 123,
     *     "old_url": "/old-category/article",
     *     "new_url": "/new-category/article",
     *     "status_code": 301,
     *     "locale": "en"
     *   }
     * }
     *
     * Response (404 - no redirect):
     * {
     *   "success": false,
     *   "message": "No redirect found for this URL"
     * }
     */
    #[Route('/lookup', name: 'lookup', methods: ['GET'])]
    public function lookupRedirect(Request $request): JsonResponse
    {
        $url = $request->query->get('url');
        $locale = $request->query->get('locale', 'ro');

        if (!$url) {
            return $this->json([
                'success' => false,
                'message' => 'URL parameter is required',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Find active redirect for this URL
        $redirect = $this->redirectRepository->findOneBy([
            'oldUrl' => $url,
            'locale' => $locale,
            'isActive' => true,
        ]);

        if (!$redirect) {
            return $this->json([
                'success' => false,
                'message' => 'No redirect found for this URL',
            ], Response::HTTP_NOT_FOUND);
        }

        // Increment hit count
        $redirect->incrementHitCount();
        $this->redirectRepository->getEntityManager()->flush();

        return $this->json([
            'success' => true,
            'redirect' => [
                'id' => $redirect->getId(),
                'old_url' => $redirect->getOldUrl(),
                'new_url' => $redirect->getNewUrl(),
                'status_code' => $redirect->getHttpStatusCode(),
                'locale' => $redirect->getLocale(),
                'type' => $redirect->getType(),
            ],
        ], Response::HTTP_OK);
    }
}
