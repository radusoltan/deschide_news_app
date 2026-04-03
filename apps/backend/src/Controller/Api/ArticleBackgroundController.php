<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Article;
use App\Service\Editorial\BackgroundGeneratorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * API endpoints for article background context generation.
 *
 * Sprint 20 — Agent Context
 */
#[Route('/api/articles', name: 'api_article_background_')]
class ArticleBackgroundController extends AbstractController
{
    public function __construct(
        private readonly BackgroundGeneratorService $backgroundService,
        private readonly EntityManagerInterface $em,
    ) {}

    /**
     * GET /api/articles/{id}/background — generează și returnează un paragraf de context istoric.
     */
    #[Route('/{id}/background', name: 'get', methods: ['GET'], requirements: ['id' => '\d+'], priority: 10)]
    public function getBackground(int $id): JsonResponse
    {
        $article = $this->em->getRepository(Article::class)->find($id);

        if ($article === null) {
            return $this->json(['error' => 'Article not found'], Response::HTTP_NOT_FOUND);
        }

        $result = $this->backgroundService->generateBackground($article);

        if ($result === null) {
            return $this->json([
                'background' => null,
                'message' => 'Nu s-a putut genera contextul — nu există suficiente surse.',
            ]);
        }

        return $this->json([
            'article_id' => $article->getId(),
            'background' => $result->backgroundText,
            'referenced_articles' => $result->referencedArticles,
            'moc_used' => $result->mocUsed,
            'sources_count' => $result->sourcesCount,
        ]);
    }

    /**
     * POST /api/articles/{id}/background/apply — inserează paragraful de context în conținutul articolului.
     */
    #[Route('/{id}/background/apply', name: 'apply', methods: ['POST'], requirements: ['id' => '\d+'], priority: 10)]
    public function applyBackground(int $id, Request $request): JsonResponse
    {
        $article = $this->em->getRepository(Article::class)->find($id);

        if ($article === null) {
            return $this->json(['error' => 'Article not found'], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $backgroundText = trim($data['background'] ?? '');

        if ($backgroundText === '') {
            return $this->json(['error' => 'Background text is required'], Response::HTTP_BAD_REQUEST);
        }

        $currentContent = $article->getContent() ?? '';
        $separator = "\n\n<hr>\n\n<strong>Context:</strong>\n\n";
        $article->setContent($currentContent . $separator . htmlspecialchars($backgroundText, \ENT_QUOTES, 'UTF-8'));

        $this->em->flush();

        return $this->json([
            'article_id' => $article->getId(),
            'applied' => true,
            'message' => 'Context adăugat la finalul articolului',
        ]);
    }
}
