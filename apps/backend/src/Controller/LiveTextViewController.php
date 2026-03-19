<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\LiveText;
use App\Service\LiveTextAnalyticsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for tracking LiveText views and viewer presence.
 */
#[Route('/api', name: 'api_')]
class LiveTextViewController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LiveTextAnalyticsService $analyticsService
    ) {
    }

    /**
     * Track a view (initial view or heartbeat).
     *
     * POST /api/live_texts/{id}/track_view
     *
     * Request body:
     * {
     *   "sessionId": "abc123...",
     *   "timeSpent": 30  // optional, seconds since last heartbeat
     * }
     */
    #[Route('/live_texts/{id}/track_view', name: 'live_text_track_view', methods: ['POST'])]
    public function trackView(int $id, Request $request): JsonResponse
    {
        $liveText = $this->entityManager->getRepository(LiveText::class)->find($id);

        if ($liveText === null) {
            return $this->json(['error' => 'Live text not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $sessionId = $data['sessionId'] ?? null;

        if ($sessionId === null || $sessionId === '') {
            return $this->json(['error' => 'Session ID is required'], Response::HTTP_BAD_REQUEST);
        }

        // Track view (creates or updates)
        $view = $this->analyticsService->trackView($liveText, $sessionId, $this->getUser());

        // Update time spent if provided
        if (isset($data['timeSpent']) && \is_int($data['timeSpent']) && $data['timeSpent'] > 0) {
            $this->analyticsService->updateTimeSpent($sessionId, $liveText, $data['timeSpent']);
        }

        // Add to active viewers in Redis
        $this->analyticsService->addActiveViewer($liveText->getId(), $sessionId);

        return $this->json([
            'success' => true,
            'viewId' => $view->getId(),
            'currentViewers' => $this->analyticsService->getActiveViewerCount($liveText->getId()),
        ]);
    }

    /**
     * Get current viewer count.
     *
     * GET /api/live_texts/{id}/viewers
     */
    #[Route('/live_texts/{id}/viewers', name: 'live_text_viewers', methods: ['GET'])]
    public function getViewers(int $id): JsonResponse
    {
        $liveText = $this->entityManager->getRepository(LiveText::class)->find($id);

        if ($liveText === null) {
            return $this->json(['error' => 'Live text not found'], Response::HTTP_NOT_FOUND);
        }

        $currentViewers = $this->analyticsService->getActiveViewerCount($liveText->getId());

        return $this->json([
            'liveTextId' => $liveText->getId(),
            'currentViewers' => $currentViewers,
        ]);
    }

    /**
     * Generate a new session ID for anonymous users.
     *
     * GET /api/live_texts/generate_session
     */
    #[Route('/live_texts/generate_session', name: 'live_text_generate_session', methods: ['GET'])]
    public function generateSession(): JsonResponse
    {
        return $this->json([
            'sessionId' => $this->analyticsService->generateSessionId(),
        ]);
    }
}
