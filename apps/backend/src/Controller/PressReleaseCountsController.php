<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\PressReleaseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class PressReleaseCountsController extends AbstractController
{
    #[Route('/api/press_releases/counts', name: 'press_release_counts', methods: ['GET'], priority: 10)]
    public function __invoke(PressReleaseRepository $repository): JsonResponse
    {
        $counts = $repository->countPendingBySourceType();
        $total = array_sum($counts);

        return $this->json([
            'email' => $counts['email'] ?? 0,
            'scrape' => $counts['scrape'] ?? 0,
            'manual' => $counts['manual'] ?? 0,
            'aggregator' => $counts['aggregator'] ?? 0,
            'total' => $total,
        ]);
    }
}
