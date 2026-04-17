<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Repository\AppSettingRepository;
use App\Repository\ArticleRepository;
use App\Service\NotebookLM\NotebookLmFactCheckService;
use App\Service\NotebookLM\NotebookLMService;
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
    private const QUESTION_MIN_LENGTH = 10;
    private const QUESTION_MAX_LENGTH = 500;

    public function __construct(
        private readonly NotebookLmFactCheckService $factCheckService,
        private readonly NotebookLMService $notebookLMService,
        private readonly ArticleRepository $articleRepository,
        private readonly RateLimiterFactoryInterface $factcheckLimiter,
        private readonly AppSettingRepository $settings,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * POST /api/admin/articles/{id}/factcheck
     *
     * Request body (optional):
     * { "question": "Custom fact-check question" }
     *
     * Response envelope (always):
     *   success: bool
     *   status:  one of 'fresh'|'cached'|'no_topics'|'no_notebook'|'disabled'|'unavailable'|'failed'|'validation'|'rate_limited'|'not_found'
     *   error?:  human-readable message (present when success=false)
     *   violations?: list<{field, message}> (only for 'validation')
     *   data?:   FactCheckResult payload (only for success)
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
                'status' => 'rate_limited',
                'error' => 'Too many requests. Try again later.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        $article = $this->articleRepository->find($id);
        if ($article === null) {
            return $this->json([
                'success' => false,
                'status' => 'not_found',
                'error' => 'Article not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Parse + validate optional custom question
        $data = json_decode($request->getContent(), true);
        $question = isset($data['question']) && \is_string($data['question']) ? trim($data['question']) : null;

        if ($question !== null && $question !== '') {
            $violations = $this->validateQuestion($question);
            if ($violations !== []) {
                return $this->json([
                    'success' => false,
                    'status' => 'validation',
                    'error' => 'Invalid question payload.',
                    'violations' => $violations,
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }
        }

        // Resolve topic from article
        $topic = $article->getTopics()->first() ?: null;
        if ($topic === null) {
            return $this->json([
                'success' => false,
                'status' => 'no_topics',
                'error' => 'Articolul nu are topicuri atribuite. Fact-check-ul necesită un topic cu notebook NotebookLM.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Differentiated 503 responses — disabled / unavailable / no_notebook
        if (!$this->settings->getBool('notebooklm.factcheck.enabled', false)) {
            return $this->json([
                'success' => false,
                'status' => 'disabled',
                'error' => 'Fact-check-ul este dezactivat din configurare (notebooklm.factcheck.enabled=false).',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if (!$this->notebookLMService->isAvailable()) {
            return $this->json([
                'success' => false,
                'status' => 'unavailable',
                'error' => 'Serviciul NotebookLM este temporar indisponibil.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        if ($topic->getNotebookLmId() === null) {
            return $this->json([
                'success' => false,
                'status' => 'no_notebook',
                'error' => 'Topicul nu are încă notebook sincronizat. Încearcă după următoarea rulare a sync-ului.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $this->logger->info('FactCheck: request received', [
            'articleId' => $id,
            'topicId' => $topic->getId(),
            'customQuestion' => $question !== null && $question !== '',
        ]);

        $result = $this->factCheckService->factCheck($article, $topic, $question !== '' ? $question : null);

        if ($result === null) {
            return $this->json([
                'success' => false,
                'status' => 'failed',
                'error' => 'NotebookLM nu a putut genera un răspuns. Încearcă din nou.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return $this->json([
            'success' => true,
            'status' => $result->cached ? 'cached' : 'fresh',
            'data' => $result->toArray(),
        ]);
    }

    /**
     * @return list<array{field: string, message: string}>
     */
    private function validateQuestion(string $question): array
    {
        $violations = [];
        $length = mb_strlen($question);

        if ($length < self::QUESTION_MIN_LENGTH) {
            $violations[] = [
                'field' => 'question',
                'message' => sprintf('Întrebarea trebuie să aibă minim %d caractere.', self::QUESTION_MIN_LENGTH),
            ];
        }
        if ($length > self::QUESTION_MAX_LENGTH) {
            $violations[] = [
                'field' => 'question',
                'message' => sprintf('Întrebarea trebuie să aibă maxim %d caractere.', self::QUESTION_MAX_LENGTH),
            ];
        }

        return $violations;
    }
}
