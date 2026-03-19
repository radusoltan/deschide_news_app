<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\LiveText;
use App\Entity\LiveTextPost;
use App\Entity\User;
use App\Service\LiveTextAdvancedAnalyticsService;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/analytics')]
class AdvancedAnalyticsController extends AbstractController
{
    public function __construct(
        private LiveTextAdvancedAnalyticsService $analyticsService,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Track engagement event
     * POST /api/analytics/track.
     */
    #[Route('/track', name: 'analytics_track_engagement', methods: ['POST'])]
    public function trackEngagement(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['post_id'], $data['engagement_type'])) {
            return $this->json([
                'error' => 'Missing required fields: post_id, engagement_type',
            ], Response::HTTP_BAD_REQUEST);
        }

        $post = $this->entityManager->getRepository(LiveTextPost::class)->find($data['post_id']);
        if (!$post) {
            return $this->json([
                'error' => 'Post not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $user = $this->getUser();

        try {
            $engagement = $this->analyticsService->trackEngagement(
                $post,
                $data['engagement_type'],
                $user instanceof User ? $user : null,
                $data['time_spent'] ?? null,
                $data['scroll_depth'] ?? null,
                $data['clicked_element'] ?? null,
                $data['metadata'] ?? null
            );

            return $this->json([
                'success' => true,
                'engagement_id' => $engagement->getId(),
            ], Response::HTTP_CREATED);
        } catch (Exception $e) {
            return $this->json([
                'error' => 'Failed to track engagement: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get heatmap data for LiveText
     * GET /api/analytics/live-text/{id}/heatmap.
     */
    #[Route('/live-text/{id}/heatmap', name: 'analytics_get_heatmap', methods: ['GET'])]
    #[IsGranted('ROLE_EDITOR')]
    public function getHeatmap(int $id): JsonResponse
    {
        $liveText = $this->entityManager->getRepository(LiveText::class)->find($id);
        if (!$liveText) {
            return $this->json([
                'error' => 'LiveText not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $heatmapData = $this->analyticsService->getHeatmapData($liveText);

        return $this->json($heatmapData);
    }

    /**
     * Get engagement funnel for LiveText
     * GET /api/analytics/live-text/{id}/funnel.
     */
    #[Route('/live-text/{id}/funnel', name: 'analytics_get_funnel', methods: ['GET'])]
    #[IsGranted('ROLE_EDITOR')]
    public function getFunnel(int $id): JsonResponse
    {
        $liveText = $this->entityManager->getRepository(LiveText::class)->find($id);
        if (!$liveText) {
            return $this->json([
                'error' => 'LiveText not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $funnelData = $this->analyticsService->getEngagementFunnel($liveText);

        return $this->json($funnelData);
    }

    /**
     * Get top engaged posts
     * GET /api/analytics/live-text/{id}/top-posts.
     */
    #[Route('/live-text/{id}/top-posts', name: 'analytics_get_top_posts', methods: ['GET'])]
    #[IsGranted('ROLE_EDITOR')]
    public function getTopPosts(int $id, Request $request): JsonResponse
    {
        $liveText = $this->entityManager->getRepository(LiveText::class)->find($id);
        if (!$liveText) {
            return $this->json([
                'error' => 'LiveText not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $limit = $request->query->getInt('limit', 10);
        $topPosts = $this->analyticsService->getTopEngagedPosts($liveText, $limit);

        return $this->json([
            'top_posts' => $topPosts,
            'limit' => $limit,
        ]);
    }

    /**
     * Get engagement summary
     * GET /api/analytics/live-text/{id}/summary.
     */
    #[Route('/live-text/{id}/summary', name: 'analytics_get_summary', methods: ['GET'])]
    #[IsGranted('ROLE_EDITOR')]
    public function getSummary(int $id): JsonResponse
    {
        $liveText = $this->entityManager->getRepository(LiveText::class)->find($id);
        if (!$liveText) {
            return $this->json([
                'error' => 'LiveText not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $summary = $this->analyticsService->getEngagementSummary($liveText);

        return $this->json($summary);
    }

    /**
     * Track post view (simplified endpoint)
     * POST /api/analytics/view.
     */
    #[Route('/view', name: 'analytics_track_view', methods: ['POST'])]
    public function trackView(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['post_id'])) {
            return $this->json([
                'error' => 'Missing required field: post_id',
            ], Response::HTTP_BAD_REQUEST);
        }

        $post = $this->entityManager->getRepository(LiveTextPost::class)->find($data['post_id']);
        if (!$post) {
            return $this->json([
                'error' => 'Post not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $user = $this->getUser();
        $this->analyticsService->trackView($post, $user instanceof User ? $user : null);

        return $this->json(['success' => true]);
    }

    /**
     * Track post read (simplified endpoint)
     * POST /api/analytics/read.
     */
    #[Route('/read', name: 'analytics_track_read', methods: ['POST'])]
    public function trackRead(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['post_id'], $data['time_spent'], $data['scroll_depth'])) {
            return $this->json([
                'error' => 'Missing required fields: post_id, time_spent, scroll_depth',
            ], Response::HTTP_BAD_REQUEST);
        }

        $post = $this->entityManager->getRepository(LiveTextPost::class)->find($data['post_id']);
        if (!$post) {
            return $this->json([
                'error' => 'Post not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $user = $this->getUser();
        $this->analyticsService->trackRead(
            $post,
            (int) $data['time_spent'],
            (int) $data['scroll_depth'],
            $user instanceof User ? $user : null
        );

        return $this->json(['success' => true]);
    }

    /**
     * Track post click (simplified endpoint)
     * POST /api/analytics/click.
     */
    #[Route('/click', name: 'analytics_track_click', methods: ['POST'])]
    public function trackClick(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['post_id'], $data['clicked_element'])) {
            return $this->json([
                'error' => 'Missing required fields: post_id, clicked_element',
            ], Response::HTTP_BAD_REQUEST);
        }

        $post = $this->entityManager->getRepository(LiveTextPost::class)->find($data['post_id']);
        if (!$post) {
            return $this->json([
                'error' => 'Post not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $user = $this->getUser();
        $this->analyticsService->trackClick(
            $post,
            $data['clicked_element'],
            $user instanceof User ? $user : null
        );

        return $this->json(['success' => true]);
    }

    /**
     * Track post share (simplified endpoint)
     * POST /api/analytics/share.
     */
    #[Route('/share', name: 'analytics_track_share', methods: ['POST'])]
    public function trackShare(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['post_id'], $data['platform'])) {
            return $this->json([
                'error' => 'Missing required fields: post_id, platform',
            ], Response::HTTP_BAD_REQUEST);
        }

        $post = $this->entityManager->getRepository(LiveTextPost::class)->find($data['post_id']);
        if (!$post) {
            return $this->json([
                'error' => 'Post not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $user = $this->getUser();
        $this->analyticsService->trackShare(
            $post,
            $data['platform'],
            $user instanceof User ? $user : null
        );

        return $this->json(['success' => true]);
    }

    /**
     * Clear old engagement data (GDPR compliance)
     * DELETE /api/analytics/clear-old.
     */
    #[Route('/clear-old', name: 'analytics_clear_old', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMIN')]
    public function clearOldEngagements(Request $request): JsonResponse
    {
        $days = $request->query->getInt('days', 90);
        $before = new DateTime("-{$days} days");

        $deletedCount = $this->analyticsService->clearOldEngagements($before);

        return $this->json([
            'success' => true,
            'deleted_count' => $deletedCount,
            'before_date' => $before->format('Y-m-d H:i:s'),
        ]);
    }
}
