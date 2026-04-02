<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Tag;
use App\Service\TagService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/tags', name: 'api_tags_')]
class TagController extends AbstractController
{
    public function __construct(
        private readonly TagService $tagService
    ) {
    }

    /**
     * Get popular tags ordered by usage count.
     *
     * Query parameters:
     * - limit: Maximum number of tags to return (default: 20, max: 100)
     * - locale: Language code (ro, en, ru) - defaults to Accept-Language header
     *
     * @example GET /api/tags/popular?limit=10&locale=ro
     */
    #[Route('/popular', name: 'popular', methods: ['GET'], priority: 2)]
    public function popular(Request $request): JsonResponse
    {
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $locale = $this->extractLocale($request);

        $tags = $this->tagService->getPopularTags($locale, $limit);

        return $this->json([
            '@context' => '/api/contexts/Tag',
            '@type' => 'hydra:Collection',
            'hydra:member' => array_map(fn (Tag $tag) => $this->serializeTag($tag), $tags),
            'hydra:totalItems' => \count($tags),
        ], Response::HTTP_OK, [
            'Cache-Control' => 'public, max-age=600', // 10 minutes
            'Vary' => 'Accept-Language',
        ]);
    }

    /**
     * Search/autocomplete tags by name.
     *
     * Query parameters:
     * - q: Search query (minimum 1 character)
     * - limit: Maximum results (default: 10, max: 50)
     * - locale: Language code (ro, en, ru)
     *
     * @example GET /api/tags/search?q=pol&limit=5&locale=ro
     */
    #[Route('/search', name: 'search', methods: ['GET'], priority: 2)]
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));
        if (empty($query)) {
            return $this->json([
                '@context' => '/api/contexts/Error',
                '@type' => 'hydra:Error',
                'hydra:title' => 'Invalid query parameter',
                'hydra:description' => 'Query parameter "q" is required and must not be empty.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $limit = min(50, max(1, (int) $request->query->get('limit', 10)));
        $locale = $this->extractLocale($request);

        $tags = $this->tagService->searchTags($query, $locale, $limit);

        return $this->json([
            '@context' => '/api/contexts/Tag',
            '@type' => 'hydra:Collection',
            'hydra:member' => array_map(fn (Tag $tag) => $this->serializeTag($tag), $tags),
            'hydra:totalItems' => \count($tags),
            'query' => $query,
        ], Response::HTTP_OK, [
            'Cache-Control' => 'public, max-age=300', // 5 minutes
            'Vary' => 'Accept-Language',
        ]);
    }

    /**
     * Get tags related to a specific tag (co-occurring tags).
     *
     * Path parameters:
     * - id: Tag ID
     *
     * Query parameters:
     * - limit: Maximum results (default: 10, max: 50)
     * - locale: Language code (ro, en, ru)
     *
     * @example GET /api/tags/5/related?limit=5&locale=ro
     */
    #[Route('/{id}/related', name: 'related', methods: ['GET'], priority: 2)]
    public function related(int $id, Request $request): JsonResponse
    {
        $tag = $this->tagService->findTagById($id);
        if (!$tag) {
            return $this->json([
                '@context' => '/api/contexts/Error',
                '@type' => 'hydra:Error',
                'hydra:title' => 'Tag not found',
                'hydra:description' => \sprintf('Tag with ID %d does not exist.', $id),
            ], Response::HTTP_NOT_FOUND);
        }

        $limit = min(50, max(1, (int) $request->query->get('limit', 10)));
        $locale = $this->extractLocale($request);

        $relatedTags = $this->tagService->getRelatedTags($tag, $locale, $limit);

        return $this->json([
            '@context' => '/api/contexts/Tag',
            '@type' => 'hydra:Collection',
            'hydra:member' => array_map(fn (Tag $t) => $this->serializeTag($t), $relatedTags),
            'hydra:totalItems' => \count($relatedTags),
            'tag' => $this->serializeTag($tag),
        ], Response::HTTP_OK, [
            'Cache-Control' => 'public, max-age=600', // 10 minutes
            'Vary' => 'Accept-Language',
        ]);
    }

    /**
     * Get tag statistics (usage count, article count, etc.).
     *
     * Path parameters:
     * - id: Tag ID
     *
     * Query parameters:
     * - locale: Language code (ro, en, ru)
     *
     * @example GET /api/tags/5/stats?locale=ro
     */
    #[Route('/{id}/stats', name: 'stats', methods: ['GET'], priority: 2)]
    public function stats(int $id, Request $request): JsonResponse
    {
        $tag = $this->tagService->findTagById($id);
        if (!$tag) {
            return $this->json([
                '@context' => '/api/contexts/Error',
                '@type' => 'hydra:Error',
                'hydra:title' => 'Tag not found',
                'hydra:description' => \sprintf('Tag with ID %d does not exist.', $id),
            ], Response::HTTP_NOT_FOUND);
        }

        $locale = $this->extractLocale($request);

        // Set locale for translation
        $tag->setTranslatableLocale($locale);

        return $this->json([
            '@context' => '/api/contexts/Tag',
            '@type' => 'TagStatistics',
            'id' => $tag->getId(),
            'name' => $tag->getName(),
            'slug' => $tag->getSlug(),
            'usageCount' => $tag->getUsageCount(),
            'articleCount' => $tag->getArticles()->count(),
            'createdAt' => $tag->getCreatedAt()?->format('c'),
            'updatedAt' => $tag->getUpdatedAt()?->format('c'),
        ], Response::HTTP_OK, [
            'Cache-Control' => 'public, max-age=300', // 5 minutes
            'Vary' => 'Accept-Language',
        ]);
    }

    /**
     * Get unused tags (tags with usageCount = 0).
     *
     * Query parameters:
     * - limit: Maximum results (default: 50, max: 200)
     * - locale: Language code (ro, en, ru)
     *
     * @example GET /api/tags/unused?limit=20&locale=ro
     */
    #[Route('/unused', name: 'unused', methods: ['GET'], priority: 2)]
    public function unused(Request $request): JsonResponse
    {
        $limit = min(200, max(1, (int) $request->query->get('limit', 50)));
        $locale = $this->extractLocale($request);

        $tags = $this->tagService->getUnusedTags($locale, $limit);

        return $this->json([
            '@context' => '/api/contexts/Tag',
            '@type' => 'hydra:Collection',
            'hydra:member' => array_map(fn (Tag $tag) => $this->serializeTag($tag), $tags),
            'hydra:totalItems' => \count($tags),
        ], Response::HTTP_OK, [
            'Cache-Control' => 'public, max-age=300', // 5 minutes
            'Vary' => 'Accept-Language',
        ]);
    }

    /**
     * Merge a source tag into a target tag.
     *
     * All articles from the source tag are moved to the target tag.
     * The source tag is deleted after merge.
     *
     * Body: { "targetTagId": 5 }
     *
     * @example POST /api/tags/10/merge
     */
    #[Route('/{id}/merge', name: 'merge', methods: ['POST'], priority: 2)]
    public function merge(int $id, Request $request): JsonResponse
    {
        $sourceTag = $this->tagService->findTagById($id);
        if (!$sourceTag) {
            return $this->json([
                '@context' => '/api/contexts/Error',
                '@type' => 'hydra:Error',
                'hydra:title' => 'Tag not found',
                'hydra:description' => \sprintf('Source tag with ID %d does not exist.', $id),
            ], Response::HTTP_NOT_FOUND);
        }

        $body = json_decode($request->getContent(), true);
        $targetTagId = $body['targetTagId'] ?? null;

        if (!$targetTagId || !\is_int($targetTagId)) {
            return $this->json([
                '@context' => '/api/contexts/Error',
                '@type' => 'hydra:Error',
                'hydra:title' => 'Invalid request',
                'hydra:description' => 'Request body must contain "targetTagId" as integer.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($targetTagId === $id) {
            return $this->json([
                '@context' => '/api/contexts/Error',
                '@type' => 'hydra:Error',
                'hydra:title' => 'Invalid merge',
                'hydra:description' => 'Cannot merge a tag into itself.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $targetTag = $this->tagService->findTagById($targetTagId);
        if (!$targetTag) {
            return $this->json([
                '@context' => '/api/contexts/Error',
                '@type' => 'hydra:Error',
                'hydra:title' => 'Target tag not found',
                'hydra:description' => \sprintf('Target tag with ID %d does not exist.', $targetTagId),
            ], Response::HTTP_NOT_FOUND);
        }

        $this->tagService->mergeTags($sourceTag, $targetTag);

        return $this->json([
            'message' => \sprintf('Tag "%s" merged into "%s" successfully.', $sourceTag->getName(), $targetTag->getName()),
            'targetTag' => $this->serializeTag($targetTag),
        ], Response::HTTP_OK);
    }

    /**
     * Extract locale from request (Accept-Language header or query param).
     */
    private function extractLocale(Request $request): string
    {
        $locale = $request->query->get('locale') ?? $request->headers->get('Accept-Language', 'ro');

        // Extract just the language code (e.g., 'en' from 'en-US')
        if (str_contains($locale, '-')) {
            $locale = explode('-', $locale)[0];
        }
        if (str_contains($locale, ',')) {
            $locale = explode(',', $locale)[0];
        }

        return $locale;
    }

    /**
     * Serialize tag to JSON-LD format.
     */
    private function serializeTag(Tag $tag): array
    {
        $data = [
            '@id' => \sprintf('/api/tags/%d', $tag->getId()),
            '@type' => 'Tag',
            'id' => $tag->getId(),
            'name' => $tag->getName(),
            'slug' => $tag->getSlug(),
            'description' => $tag->getDescription(),
            'usageCount' => $tag->getUsageCount(),
        ];

        if ($tag->getTranslatedSlugs()) {
            $data['translatedSlugs'] = $tag->getTranslatedSlugs();
        }

        return $data;
    }
}
