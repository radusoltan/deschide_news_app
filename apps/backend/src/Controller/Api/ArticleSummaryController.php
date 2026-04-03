<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Article;
use App\Service\Editorial\InternalSummaryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API endpoints for article internal summaries (TL;DR).
 *
 * Sprint 20 — Agent Sinteză
 */
#[Route('/api/articles', name: 'api_article_summary_')]
class ArticleSummaryController extends AbstractController
{
    public function __construct(
        private readonly InternalSummaryService $summaryService,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * GET /api/articles/{id}/summary — returnează summary-ul existent.
     */
    #[Route('/{id}/summary', name: 'get', methods: ['GET'], requirements: ['id' => '\d+'], priority: 10)]
    public function getSummary(int $id): JsonResponse
    {
        $article = $this->em->getRepository(Article::class)->find($id);

        if ($article === null) {
            return $this->json(['error' => 'Article not found'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'article_id' => $article->getId(),
            'summary' => $article->getInternalSummary(),
            'has_summary' => $article->getInternalSummary() !== null,
        ]);
    }

    /**
     * POST /api/articles/{id}/summary/regenerate — regenerează summary-ul cu Gemini.
     */
    #[Route('/{id}/summary/regenerate', name: 'regenerate', methods: ['POST'], requirements: ['id' => '\d+'], priority: 10)]
    public function regenerateSummary(int $id): JsonResponse
    {
        $article = $this->em->getRepository(Article::class)->find($id);

        if ($article === null) {
            return $this->json(['error' => 'Article not found'], Response::HTTP_NOT_FOUND);
        }

        $bodyText = strip_tags($article->getContent() ?? '');
        $title = $article->getTitle() ?? '';

        $summary = $this->summaryService->generateSummary($title, $bodyText);

        if ($summary === null) {
            return $this->json([
                'error' => 'Nu s-a putut genera summary-ul',
                'article_id' => $article->getId(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $article->setInternalSummary($summary);
        $this->em->flush();

        return $this->json([
            'article_id' => $article->getId(),
            'summary' => $summary,
            'regenerated' => true,
        ]);
    }
}
