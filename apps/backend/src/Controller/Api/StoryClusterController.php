<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\StoryCluster;
use App\Enum\StoryClusterStatus;
use App\Repository\StoryClusterRepository;
use App\Service\Clustering\ImportanceScoreCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/api/story-clusters')]
class StoryClusterController extends AbstractController
{
    public function __construct(
        private readonly StoryClusterRepository $clusterRepository,
        private readonly EntityManagerInterface $em,
        private readonly ImportanceScoreCalculator $calculator,
        private readonly SerializerInterface $serializer,
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

        $cluster->setStatus(StoryClusterStatus::PROMOTED);
        $cluster->setPromotedToPressRelease(true);
        $this->em->flush();

        return $this->json([
            'id' => $cluster->getId(),
            'status' => $cluster->getStatus()->value,
            'promotedToPressRelease' => true,
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
}
