<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Service\Aggregator\AggregatorStatsCollector;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/aggregator')]
#[IsGranted('ROLE_EDITOR')]
class AggregatorStatsController extends AbstractController
{
    public function __construct(
        private readonly AggregatorStatsCollector $statsCollector,
    ) {}

    #[Route('/stats', name: 'api_aggregator_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $stats = $this->statsCollector->getCachedStats();

        if ($stats === []) {
            $stats = $this->statsCollector->collectAndCacheStats();
        }

        return $this->json($stats);
    }
}
