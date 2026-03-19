<?php

declare(strict_types=1);

namespace App\Controller\LiveText;

use App\Dto\LiveText\ViewersCountEventDto;
use App\Service\LiveTextNotificationService;
use DateTime;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Test controller for Mercure integration.
 */
#[Route('/api/live-texts', name: 'api_live_text_')]
class TestMercureController extends AbstractController
{
    public function __construct(
        private readonly LiveTextNotificationService $notificationService
    ) {
    }

    /**
     * Test Mercure publishing.
     */
    #[Route('/test-mercure/{liveTextId}', name: 'test_mercure', methods: ['POST'])]
    public function testMercure(int $liveTextId, Request $request): JsonResponse
    {
        $count = $request->query->getInt('count', 100);

        // Publish a test viewers count event
        $event = new ViewersCountEventDto(
            liveTextId: $liveTextId,
            count: $count,
            timestamp: new DateTime()
        );

        $this->notificationService->publishEvent($event);

        return new JsonResponse([
            'success' => true,
            'message' => 'Test event published to Mercure',
            'liveTextId' => $liveTextId,
            'topic' => $this->notificationService->getTopicUrl($liveTextId),
            'event' => $event->toArray(),
        ]);
    }

    /**
     * Get Mercure hub information.
     */
    #[Route('/mercure-info', name: 'mercure_info', methods: ['GET'])]
    public function mercureInfo(): JsonResponse
    {
        return new JsonResponse([
            'mercureHubUrl' => $this->notificationService->getMercureHubUrl(),
            'exampleTopic' => $this->notificationService->getTopicUrl(1),
            'instructions' => [
                'To subscribe from JavaScript:',
                'const eventSource = new EventSource(\'<mercureHubUrl>?topic=' . urlencode($this->notificationService->getTopicUrl(1)) . '\');',
                'eventSource.onmessage = (event) => console.log(JSON.parse(event.data));',
            ],
        ]);
    }
}
