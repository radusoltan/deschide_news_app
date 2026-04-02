<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Message\OptimizeSeoMessage;
use App\MessageHandler\OptimizeSeoHandler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api', name: 'api_seo_')]
class SeoController extends AbstractController
{
    public function __construct(
        private readonly OptimizeSeoHandler $handler,
    ) {
    }

    /**
     * Trigger SEO optimization for an article via Gemini AI.
     *
     * Generates metaTitle, metaDescription, and suggests tags (all in Romanian).
     * This is a synchronous endpoint — waits for Gemini to respond.
     *
     * Request body (optional JSON):
     * {
     *   "force": false,
     *   "generateMeta": true,
     *   "suggestTags": true
     * }
     */
    #[Route('/articles/{id}/optimize-seo', name: 'optimize_seo', methods: ['POST'])]
    public function optimizeSeo(int $id, Request $request): JsonResponse
    {
        // Parse optional body
        $body = [];
        $content = $request->getContent();
        if ($content !== '' && $content !== '{}') {
            try {
                $body = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                // Ignore invalid body — use defaults
            }
        }

        $message = new OptimizeSeoMessage(
            articleId: $id,
            generateMeta: (bool) ($body['generateMeta'] ?? true),
            suggestTags: (bool) ($body['suggestTags'] ?? true),
            force: (bool) ($body['force'] ?? false),
        );

        $result = $this->handler->handle($message);

        if ($result === null) {
            return $this->json([
                'success' => false,
                'error' => 'SEO optimization failed. Article may not exist, have insufficient content, or already be optimized.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->json([
            'success' => true,
            'metaTitle' => $result['metaTitle'],
            'metaDescription' => $result['metaDescription'],
            'tagsAdded' => $result['tagsAdded'],
            'tagsExisting' => $result['tagsExisting'],
        ]);
    }
}
