<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Enum\PressReleaseStatus;
use App\Enum\SourceType;
use App\Repository\PressReleaseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/api/aggregator')]
#[IsGranted('ROLE_EDITOR')]
class DedupStatsController extends AbstractController
{
    public function __construct(
        private readonly PressReleaseRepository $repository,
        private readonly TagAwareCacheInterface $cache,
    ) {}

    #[Route('/dedup-stats', name: 'api_aggregator_dedup_stats', methods: ['GET'])]
    public function dedupStats(): JsonResponse
    {
        $stats = $this->cache->get('aggregator_dedup_stats', function (ItemInterface $item) {
            $item->expiresAfter(1800); // 30 min TTL
            $item->tag(['dedup_stats', 'aggregator_stats']);

            return $this->computeStats();
        });

        return $this->json($stats);
    }

    private function computeStats(): array
    {
        $since = new \DateTimeImmutable('-7 days');

        // Query aggregator press releases from last 7 days
        $qb = $this->repository->createQueryBuilder('pr')
            ->where('pr.sourceType = :sourceType')
            ->andWhere('pr.receivedAt >= :since')
            ->setParameter('sourceType', SourceType::AGGREGATOR)
            ->setParameter('since', $since);

        $allItems = $qb->getQuery()->getResult();

        // Compute totals
        $total = count($allItems);
        $uniqueHashes = [];
        $duplicateCount = 0;
        $pendingCount = 0;
        $daily = [];

        foreach ($allItems as $pr) {
            $hash = $pr->getContentHash();
            $day = $pr->getReceivedAt()->format('Y-m-d');

            if (!isset($daily[$day])) {
                $daily[$day] = ['date' => $day, 'unique' => 0, 'duplicate' => 0, 'review' => 0];
            }

            if ($hash && isset($uniqueHashes[$hash])) {
                $duplicateCount++;
                $daily[$day]['duplicate']++;
            } else {
                if ($hash) {
                    $uniqueHashes[$hash] = true;
                }
                $daily[$day]['unique']++;
            }

            if ($pr->getStatus() === PressReleaseStatus::PENDING) {
                $pendingCount++;
                $daily[$day]['review']++;
            }
        }

        $uniqueCount = $total - $duplicateCount;

        // Sort daily by date
        ksort($daily);

        return [
            'totals' => [
                'total' => $total,
                'unique' => $uniqueCount,
                'duplicate' => $duplicateCount,
                'pendingReview' => $pendingCount,
            ],
            'daily' => array_values($daily),
        ];
    }
}
