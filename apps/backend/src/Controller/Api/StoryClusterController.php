<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\PressRelease;
use App\Entity\StoryCluster;
use App\Enum\StoryClusterStatus;
use App\Message\Clustering\SummarizeClusterMessage;
use App\Repository\PressReleaseRepository;
use App\Repository\StoryClusterRepository;
use App\Service\Clustering\AutoPromoteService;
use App\Service\Clustering\ImportanceScoreCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/story-clusters')]
class StoryClusterController extends AbstractController
{
    public function __construct(
        private readonly StoryClusterRepository $clusterRepository,
        private readonly PressReleaseRepository $pressReleaseRepository,
        private readonly EntityManagerInterface $em,
        private readonly ImportanceScoreCalculator $calculator,
        private readonly AutoPromoteService $autoPromoteService,
        private readonly SerializerInterface $serializer,
        private readonly MessageBusInterface $messageBus,
    ) {}

    #[Route('/top', name: 'api_story_clusters_top', methods: ['GET'])]
    public function top(Request $request): JsonResponse
    {
        $limit = min(50, max(1, (int) $request->query->get('limit', '10')));
        $sinceHours = max(1, (int) $request->query->get('since_hours', '24'));
        $since = new \DateTimeImmutable(sprintf('-%d hours', $sinceHours));

        $clusters = $this->clusterRepository->findTopByScore($limit, $since);

        $data = $this->serializer->normalize($clusters, 'json', [
            'groups' => ['cluster:read'],
            'enable_max_depth' => true,
        ]);

        return $this->json($data);
    }

    #[Route('/{id}/promote', name: 'api_story_clusters_promote', methods: ['POST'])]
    public function promote(StoryCluster $cluster): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        if ($cluster->isPromotedToPressRelease()) {
            return $this->json(['error' => 'Cluster already promoted'], 400);
        }

        $pr = $this->autoPromoteService->promoteCluster($cluster);
        $this->em->flush();

        return $this->json([
            'id' => $cluster->getId(),
            'status' => $cluster->getStatus()->value,
            'promotedToPressRelease' => true,
            'pressReleaseId' => $pr?->getId(),
        ]);
    }

    #[Route('/{id}/score', name: 'api_story_clusters_score', methods: ['POST'])]
    public function recalculateScore(StoryCluster $cluster): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        $newScore = $this->calculator->calculate($cluster);
        $cluster->setImportanceScore($newScore);
        $this->em->flush();

        return $this->json([
            'id' => $cluster->getId(),
            'importanceScore' => $newScore,
        ]);
    }

    #[Route('/{id}/regenerate-summary', name: 'api_story_clusters_regenerate_summary', methods: ['POST'])]
    public function regenerateSummary(StoryCluster $cluster): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        $this->messageBus->dispatch(new SummarizeClusterMessage($cluster->getId()));

        return $this->json([
            'id' => $cluster->getId(),
            'message' => 'Summary regeneration dispatched',
        ], 202);
    }

    #[Route('/{id}/remove-press-release', name: 'api_story_clusters_remove_pr', methods: ['PATCH'])]
    public function removePressRelease(StoryCluster $cluster, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        $data = json_decode($request->getContent(), true);
        $pressReleaseId = $data['pressReleaseId'] ?? null;

        if ($pressReleaseId === null) {
            return $this->json(['error' => 'pressReleaseId required'], 400);
        }

        $pressRelease = $this->pressReleaseRepository->find($pressReleaseId);
        if ($pressRelease === null || !$cluster->getPressReleases()->contains($pressRelease)) {
            return $this->json(['error' => 'PressRelease not found in cluster'], 404);
        }

        $cluster->removePressRelease($pressRelease);
        $cluster->recalculateCounts();

        if ($cluster->getPressReleases()->isEmpty()) {
            $cluster->setStatus(StoryClusterStatus::ARCHIVED);
        }

        $this->em->flush();

        return $this->json([
            'success' => true,
            'articleCount' => $cluster->getArticleCount(),
            'sourceCount' => $cluster->getSourceCount(),
        ]);
    }
}
