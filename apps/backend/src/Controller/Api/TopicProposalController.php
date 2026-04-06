<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\TopicRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/topics')]
#[IsGranted('ROLE_EDITOR')]
class TopicProposalController extends AbstractController
{
    public function __construct(
        private readonly TopicRepository $topicRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    #[Route('/proposals', name: 'api_topics_proposals', methods: ['GET'])]
    public function proposals(): JsonResponse
    {
        $topics = $this->topicRepository->findBy(
            ['reviewStatus' => 'pending_review'],
            ['createdAt' => 'DESC'],
        );

        $result = [];
        foreach ($topics as $topic) {
            $result[] = [
                'id' => $topic->getId(),
                'title' => $topic->getTitle(),
                'reviewStatus' => $topic->getReviewStatus(),
                'isActive' => $topic->isActive(),
                'createdAt' => $topic->getCreatedAt()?->format('c'),
            ];
        }

        return $this->json($result);
    }

    #[Route('/{id}/approve', name: 'api_topics_approve', methods: ['POST'])]
    public function approve(int $id): JsonResponse
    {
        $topic = $this->topicRepository->find($id);
        if ($topic === null) {
            throw new NotFoundHttpException('Topic not found');
        }

        $topic->setReviewStatus('approved');
        $topic->setIsActive(true);
        $this->em->flush();

        return $this->json([
            'id' => $topic->getId(),
            'title' => $topic->getTitle(),
            'reviewStatus' => 'approved',
        ]);
    }

    #[Route('/{id}/reject', name: 'api_topics_reject', methods: ['POST'])]
    public function reject(int $id): JsonResponse
    {
        $topic = $this->topicRepository->find($id);
        if ($topic === null) {
            throw new NotFoundHttpException('Topic not found');
        }

        $topic->setReviewStatus('rejected');
        $topic->setIsActive(false);
        $this->em->flush();

        return $this->json([
            'id' => $topic->getId(),
            'title' => $topic->getTitle(),
            'reviewStatus' => 'rejected',
        ]);
    }
}
