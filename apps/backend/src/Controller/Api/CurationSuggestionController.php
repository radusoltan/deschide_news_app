<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\CurationSuggestion;
use App\Enum\CurationSuggestionStatus;
use App\Enum\CurationSuggestionType;
use App\Enum\StoryClusterStatus;
use App\Repository\CurationSuggestionRepository;
use App\Repository\StoryClusterRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/curation-suggestions')]
#[IsGranted('ROLE_EDITOR')]
final class CurationSuggestionController extends AbstractController
{
    public function __construct(
        private readonly CurationSuggestionRepository $repository,
        private readonly StoryClusterRepository $clusterRepository,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('/{id}/accept', name: 'api_curation_accept', methods: ['POST'])]
    public function accept(int $id): JsonResponse
    {
        $suggestion = $this->repository->find($id);
        if ($suggestion === null) {
            return $this->json(['error' => 'Suggestion not found'], Response::HTTP_NOT_FOUND);
        }

        if ($suggestion->getStatus() !== CurationSuggestionStatus::PENDING) {
            return $this->json(['error' => 'Suggestion already resolved'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = match ($suggestion->getType()) {
            CurationSuggestionType::MERGE => $this->executeMerge($suggestion),
            CurationSuggestionType::ARCHIVE => $this->executeArchive($suggestion),
            CurationSuggestionType::RETAG => $this->executeRetag($suggestion),
        };

        if ($result !== null) {
            return $this->json(['error' => $result], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $suggestion->setStatus(CurationSuggestionStatus::ACCEPTED);
        $suggestion->setResolvedAt(new \DateTimeImmutable());
        $suggestion->setResolvedBy($this->getUser()?->getUserIdentifier() ?? 'system');

        $this->em->flush();

        $this->logger->info('CurationSuggestion accepted', [
            'id' => $id,
            'type' => $suggestion->getType()->value,
        ]);

        return $this->json(['success' => true, 'id' => $id, 'type' => $suggestion->getType()->value]);
    }

    #[Route('/{id}/reject', name: 'api_curation_reject', methods: ['POST'])]
    public function reject(int $id): JsonResponse
    {
        $suggestion = $this->repository->find($id);
        if ($suggestion === null) {
            return $this->json(['error' => 'Suggestion not found'], Response::HTTP_NOT_FOUND);
        }

        if ($suggestion->getStatus() !== CurationSuggestionStatus::PENDING) {
            return $this->json(['error' => 'Suggestion already resolved'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $suggestion->setStatus(CurationSuggestionStatus::REJECTED);
        $suggestion->setResolvedAt(new \DateTimeImmutable());
        $suggestion->setResolvedBy($this->getUser()?->getUserIdentifier() ?? 'system');

        $this->em->flush();

        return $this->json(['success' => true, 'id' => $id]);
    }

    private function executeMerge(CurationSuggestion $suggestion): ?string
    {
        $targetId = $suggestion->getTargetClusterId();
        if ($targetId === null) {
            return 'No target cluster specified';
        }

        $target = $this->clusterRepository->find($targetId);
        if ($target === null) {
            return 'Target cluster not found';
        }

        foreach ($suggestion->getClusterIds() as $clusterId) {
            if ($clusterId === $targetId) {
                continue;
            }

            $source = $this->clusterRepository->find($clusterId);
            if ($source === null) {
                continue;
            }

            // Move all press releases to target
            foreach ($source->getPressReleases() as $pr) {
                $target->addPressRelease($pr);
            }

            // Archive the source cluster
            $source->setStatus(StoryClusterStatus::ARCHIVED);
        }

        $target->recalculateCounts();

        return null;
    }

    private function executeArchive(CurationSuggestion $suggestion): ?string
    {
        foreach ($suggestion->getClusterIds() as $clusterId) {
            $cluster = $this->clusterRepository->find($clusterId);
            if ($cluster === null) {
                return sprintf('Cluster %d not found', $clusterId);
            }
            $cluster->setStatus(StoryClusterStatus::ARCHIVED);
        }

        return null;
    }

    private function executeRetag(CurationSuggestion $suggestion): ?string
    {
        $topic = $suggestion->getSuggestedTopic();
        if ($topic === null) {
            return 'No suggested topic';
        }

        // RETAG is a future feature — for now, just mark as accepted
        // The actual topic entity linking can be added when the taxonomy is richer

        return null;
    }
}
