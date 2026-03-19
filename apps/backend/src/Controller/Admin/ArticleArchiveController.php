<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\Article;
use App\Enum\ArchiveReason;
use App\Enum\ArticleStatus;
use App\Repository\ArticleRepository;
use App\Service\ArticleArchiveService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use ValueError;

/**
 * Article Archive Management Controller.
 *
 * Provides admin endpoints for archiving and unarchiving articles,
 * bulk operations, and archive statistics.
 *
 * All routes require ROLE_ADMIN authentication.
 */
#[Route('/api/admin')]
#[IsGranted('ROLE_ADMIN')]
class ArticleArchiveController extends AbstractController
{
    public function __construct(
        private readonly ArticleArchiveService $archiveService,
        private readonly ArticleRepository $articleRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Archive a single article.
     *
     * POST /api/admin/articles/{id}/archive
     *
     * Request body:
     * {
     *   "reason": "old_content"
     * }
     *
     * Response (200):
     * {
     *   "success": true,
     *   "message": "Article archived successfully",
     *   "article": {
     *     "id": 123,
     *     "title": "Article Title",
     *     "status": "archived",
     *     "archived_at": "2025-11-30T10:30:00+00:00",
     *     "archive_reason": "old_content"
     *   }
     * }
     *
     * Response (400):
     * {
     *   "success": false,
     *   "error": "Invalid archive reason. Must be one of: old_content, outdated_info, legal_request, duplicate, low_quality, policy_violation, manual"
     * }
     *
     * Response (404):
     * {
     *   "success": false,
     *   "error": "Article not found"
     * }
     */
    #[Route('/articles/{id}/archive', name: 'admin_article_archive', methods: ['POST'])]
    public function archiveArticle(int $id, Request $request): JsonResponse
    {
        $article = $this->articleRepository->find($id);

        if (!$article) {
            return $this->json([
                'success' => false,
                'error' => 'Article not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Get and validate request data
        $data = json_decode($request->getContent(), true);

        if (!isset($data['reason'])) {
            return $this->json([
                'success' => false,
                'error' => 'Missing required field: reason',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate ArchiveReason enum
        try {
            $archiveReason = ArchiveReason::from($data['reason']);
        } catch (ValueError $e) {
            $validReasons = array_map(fn ($case) => $case->value, ArchiveReason::cases());

            return $this->json([
                'success' => false,
                'error' => 'Invalid archive reason. Must be one of: ' . implode(', ', $validReasons),
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if already archived
        if ($article->getStatus() === ArticleStatus::ARCHIVED) {
            return $this->json([
                'success' => false,
                'error' => 'Article is already archived',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Archive the article using the service
            $this->archiveService->archiveArticle($article, $archiveReason);

            // Log the action for audit trail
            $user = $this->getUser();
            $this->logger->info('Article archived by admin', [
                'article_id' => $article->getId(),
                'article_title' => $article->getTitle(),
                'reason' => $archiveReason->value,
                'admin_user' => $user?->getUserIdentifier(),
                'admin_id' => method_exists($user, 'getId') ? $user->getId() : null,
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Article archived successfully',
                'article' => [
                    'id' => $article->getId(),
                    'title' => $article->getTitle(),
                    'status' => $article->getStatus()->value,
                    'archived_at' => $article->getArchivedAt()?->format('c'),
                    'archive_reason' => $article->getArchiveReason()?->value,
                ],
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            $this->logger->error('Failed to archive article', [
                'article_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to archive article: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Unarchive (restore) a single article.
     *
     * POST /api/admin/articles/{id}/unarchive
     *
     * Response (200):
     * {
     *   "success": true,
     *   "message": "Article unarchived successfully",
     *   "article": {
     *     "id": 123,
     *     "title": "Article Title",
     *     "status": "published",
     *     "archived_at": null,
     *     "archive_reason": null
     *   }
     * }
     *
     * Response (400):
     * {
     *   "success": false,
     *   "error": "Article is not archived"
     * }
     *
     * Response (404):
     * {
     *   "success": false,
     *   "error": "Article not found"
     * }
     */
    #[Route('/articles/{id}/unarchive', name: 'admin_article_unarchive', methods: ['POST'])]
    public function unarchiveArticle(int $id): JsonResponse
    {
        $article = $this->articleRepository->find($id);

        if (!$article) {
            return $this->json([
                'success' => false,
                'error' => 'Article not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if article is archived
        if ($article->getStatus() !== ArticleStatus::ARCHIVED) {
            return $this->json([
                'success' => false,
                'error' => 'Article is not archived',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Unarchive the article using the service
            $this->archiveService->unarchiveArticle($article);

            // Log the action for audit trail
            $user = $this->getUser();
            $this->logger->info('Article unarchived by admin', [
                'article_id' => $article->getId(),
                'article_title' => $article->getTitle(),
                'admin_user' => $user?->getUserIdentifier(),
                'admin_id' => method_exists($user, 'getId') ? $user->getId() : null,
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Article unarchived successfully',
                'article' => [
                    'id' => $article->getId(),
                    'title' => $article->getTitle(),
                    'status' => $article->getStatus()->value,
                    'archived_at' => $article->getArchivedAt()?->format('c'),
                    'archive_reason' => $article->getArchiveReason()?->value,
                ],
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            $this->logger->error('Failed to unarchive article', [
                'article_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to unarchive article: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Bulk archive old articles.
     *
     * POST /api/admin/articles/archive-bulk
     *
     * Request body:
     * {
     *   "years_old": 4,
     *   "batch_size": 100
     * }
     *
     * Response (200):
     * {
     *   "success": true,
     *   "message": "Bulk archive completed",
     *   "results": {
     *     "total_archived": 152,
     *     "batch_size": 100,
     *     "years_old": 4,
     *     "cutoff_date": "2021-11-30T00:00:00+00:00"
     *   }
     * }
     *
     * Response (400):
     * {
     *   "success": false,
     *   "error": "Invalid parameters: years_old must be positive"
     * }
     */
    #[Route('/articles/archive-bulk', name: 'admin_article_archive_bulk', methods: ['POST'])]
    public function bulkArchiveOldArticles(Request $request): JsonResponse
    {
        // Get and validate request data
        $data = json_decode($request->getContent(), true);

        $yearsOld = $data['years_old'] ?? 4;
        $batchSize = $data['batch_size'] ?? 100;

        // Validate parameters
        if ($yearsOld < 1) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid parameters: years_old must be positive',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($batchSize < 1 || $batchSize > 1000) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid parameters: batch_size must be between 1 and 1000',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Perform bulk archive operation using the service
            $result = $this->archiveService->bulkArchiveOldArticles($yearsOld, $batchSize);

            // Log the bulk operation for audit trail
            $user = $this->getUser();
            $this->logger->info('Bulk archive operation completed by admin', [
                'total_archived' => $result['total_archived'],
                'years_old' => $yearsOld,
                'batch_size' => $batchSize,
                'cutoff_date' => $result['cutoff_date']->format('c'),
                'admin_user' => $user?->getUserIdentifier(),
                'admin_id' => method_exists($user, 'getId') ? $user->getId() : null,
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Bulk archive completed',
                'results' => [
                    'total_archived' => $result['total_archived'],
                    'batch_size' => $batchSize,
                    'years_old' => $yearsOld,
                    'cutoff_date' => $result['cutoff_date']->format('c'),
                ],
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            $this->logger->error('Bulk archive operation failed', [
                'years_old' => $yearsOld,
                'batch_size' => $batchSize,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Bulk archive operation failed: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get detailed archive statistics.
     *
     * GET /api/admin/archive/stats
     *
     * Response (200):
     * {
     *   "success": true,
     *   "statistics": {
     *     "total_articles": 15234,
     *     "archived_articles": 3542,
     *     "archive_percentage": 23.25,
     *     "by_reason": {
     *       "old_content": 2845,
     *       "outdated_info": 342,
     *       "legal_request": 12,
     *       "duplicate": 89,
     *       "low_quality": 154,
     *       "policy_violation": 45,
     *       "manual": 55
     *     },
     *     "archived_this_month": 128,
     *     "archived_this_year": 1542,
     *     "oldest_archived": {
     *       "id": 45,
     *       "title": "Old Article",
     *       "archived_at": "2021-03-15T10:00:00+00:00",
     *       "reason": "old_content"
     *     },
     *     "most_recent_archived": {
     *       "id": 8932,
     *       "title": "Recent Archive",
     *       "archived_at": "2025-11-30T09:30:00+00:00",
     *       "reason": "duplicate"
     *     }
     *   },
     *   "timestamp": "2025-11-30T10:30:00+00:00"
     * }
     */
    #[Route('/archive/stats', name: 'admin_archive_stats', methods: ['GET'])]
    public function getArchiveStatistics(): JsonResponse
    {
        try {
            // Get statistics from the service
            $statistics = $this->archiveService->getArchiveStatistics();

            return $this->json([
                'success' => true,
                'statistics' => $statistics,
                'timestamp' => new DateTimeImmutable()->format('c'),
            ], Response::HTTP_OK);
        } catch (Exception $e) {
            $this->logger->error('Failed to retrieve archive statistics', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Failed to retrieve statistics: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
