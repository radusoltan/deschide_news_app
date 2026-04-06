<?php

declare(strict_types=1);

namespace App\Controller\Api;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Cache\CacheInterface;

#[Route('/api/aggregator')]
#[IsGranted('ROLE_EDITOR')]
class AggregatorStatsController extends AbstractController
{
    private const STATS_CACHE_KEY = 'aggregator_source_stats';

    public function __construct(
        private readonly CacheInterface $cache,
    ) {}

    #[Route('/stats', name: 'api_aggregator_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $stats = $this->cache->get(self::STATS_CACHE_KEY, fn () => []);

        return $this->json($stats);
    }
}
