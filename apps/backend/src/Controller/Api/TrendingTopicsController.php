<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\Aggregator\TrendScoringService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/topics')]
#[IsGranted('ROLE_EDITOR')]
class TrendingTopicsController extends AbstractController
{
    public function __construct(
        private readonly TrendScoringService $scoringService,
    ) {}

    #[Route('/trending', name: 'api_topics_trending', methods: ['GET'])]
    public function trending(Request $request): JsonResponse
    {
        $days = $request->query->getInt('days', 7);
        $limit = $request->query->getInt('limit', 20);

        $days = max(1, min(30, $days));
        $limit = max(1, min(50, $limit));

        $trending = $this->scoringService->getTopTrendingTopics($days, $limit);

        return $this->json($trending);
    }
}
