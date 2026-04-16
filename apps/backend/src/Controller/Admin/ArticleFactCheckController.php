<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\ArticleRepository;
use App\Service\NotebookLM\NotebookLmFactCheckService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin')]
#[IsGranted('ROLE_EDITOR')]
class ArticleFactCheckController extends AbstractController
{
    public function __construct(
        private readonly NotebookLmFactCheckService $factCheckService,
        private readonly ArticleRepository $articleRepository,
        private readonly RateLimiterFactoryInterface $factcheckLimiter,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * POST /api/admin/articles/{id}/factcheck
     *
     * Request body (optional):
     * { "question": "Custom fact-check question" }
     *
     * Returns 200 with FactCheckResult or 503 if NotebookLM is unavailable.
     */
    #[Route('/articles/{id}/factcheck', name: 'admin_article_factcheck', methods: ['POST'])]
    public function factCheck(int $id, Request $request): JsonResponse
    {
        // Rate limiting: 20 req/min per user
        $user = $this->getUser();
        $limiter = $this->factcheckLimiter->create($user?->getUserIdentifier() ?? $request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return $this->json([
                'success' => false,
                'error' => 'Too many requests. Try again later.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $article = $this->articleRepository->find($id);
        if ($article === null) {
            return $this->json([
                'success' => false,
                'error' => 'Article not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Resolve topic from article
        $topic = $article->getTopics()->first() ?: null;
        if ($topic === null) {
            return $this->json([
                'success' => false,
                'error' => 'Article has no assigned topics. Fact-check requires a topic with a NotebookLM notebook.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check availability
        if (!$this->factCheckService->isAvailableForTopic($topic)) {
            return $this->json([
                'success' => false,
                'error' => 'NotebookLM fact-check is not available for this topic. The topic may not have a notebook assigned, or NotebookLM is disabled.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        // Parse optional custom question
        $data = json_decode($request->getContent(), true);
        $question = $data['question'] ?? null;

        $this->logger->info('FactCheck: request received', [
            'articleId' => $id,
            'topicId' => $topic->getId(),
            'customQuestion' => $question !== null,
        ]);

        $result = $this->factCheckService->factCheck($article, $topic, $question);

        if ($result === null) {
            return $this->json([
                'success' => false,
                'error' => 'Fact-check query failed. NotebookLM may be unavailable or the notebook has no sources.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->json([
            'success' => true,
            'data' => $result->toArray(),
        ]);
    }
}
