<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Topic;
use App\Repository\TopicRepository;
use App\Service\TopicService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

#[Route('/api/topics', name: 'api_topics_')]
class TopicController extends AbstractController
{
    public function __construct(
        private readonly TopicService $topicService,
        private readonly TopicRepository $topicRepository,
        private readonly TagAwareCacheInterface $cache,
    ) {}

    /**
     * Get the full topic tree.
     */
    #[Route('/tree', name: 'tree', methods: ['GET'], priority: 2)]
    public function tree(Request $request): JsonResponse
    {
        $locale = $this->extractLocale($request);

        $tree = $this->cache->get(
            'topics_tree_' . $locale,
            function (ItemInterface $item) use ($locale) {
                $item->expiresAfter(3600);
                $item->tag(['topics_tree']);

                return $this->topicService->getTreeForApi($locale);
            }
        );

        return $this->json($tree, Response::HTTP_OK, [
            'Cache-Control' => 'public, max-age=600',
            'Vary' => 'Accept-Language',
        ]);
    }

    /**
     * Get breadcrumb path for a topic.
     */
    #[Route('/{id}/path', name: 'path', methods: ['GET'], priority: 2)]
    public function path(int $id, Request $request): JsonResponse
    {
        $topic = $this->topicRepository->find($id);
        if (!$topic) {
            return $this->json([
                '@type' => 'hydra:Error',
                'hydra:title' => 'Not Found',
                'hydra:description' => \sprintf('Topic with ID %d not found.', $id),
            ], Response::HTTP_NOT_FOUND);
        }

        $locale = $this->extractLocale($request);
        $path = $this->topicService->getPath($topic);

        $result = array_map(fn (Topic $t) => [
            'id' => $t->getId(),
            'title' => $t->getTitle(),
            'slug' => $t->getSlug(),
            'lvl' => $t->getLvl(),
        ], $path);

        return $this->json($result, Response::HTTP_OK, [
            'Cache-Control' => 'public, max-age=600',
            'Vary' => 'Accept-Language',
        ]);
    }

    /**
     * Get articles for a topic (including descendants), paginated.
     */
    #[Route('/{id}/articles', name: 'articles', methods: ['GET'], priority: 2)]
    public function articles(int $id, Request $request): JsonResponse
    {
        $topic = $this->topicRepository->find($id);
        if (!$topic) {
            return $this->json([
                '@type' => 'hydra:Error',
                'hydra:title' => 'Not Found',
                'hydra:description' => \sprintf('Topic with ID %d not found.', $id),
            ], Response::HTTP_NOT_FOUND);
        }

        $locale = $this->extractLocale($request);
        $page = max(1, (int) $request->query->get('page', 1));
        $itemsPerPage = min(50, max(1, (int) $request->query->get('itemsPerPage', 20)));

        // Get all topic IDs (self + descendants)
        $topicIds = [$topic->getId()];
        $descendants = $this->topicRepository->children($topic);
        foreach ($descendants as $descendant) {
            $topicIds[] = $descendant->getId();
        }

        $qb = $this->topicRepository->getEntityManager()->createQueryBuilder()
            ->select('a')
            ->from(\App\Entity\Article::class, 'a')
            ->leftJoin('a.topics', 't')
            ->where('t.id IN (:topicIds)')
            ->andWhere('a.status = :status')
            ->setParameter('topicIds', $topicIds)
            ->setParameter('status', \App\Enum\ArticleStatus::PUBLISHED)
            ->orderBy('a.publishedAt', 'DESC')
            ->setFirstResult(($page - 1) * $itemsPerPage)
            ->setMaxResults($itemsPerPage);

        $query = $qb->getQuery();
        $query->setHint(
            \Gedmo\Translatable\TranslatableListener::HINT_TRANSLATABLE_LOCALE,
            $locale
        );

        $articles = $query->getResult();

        // Get total count
        $countQb = $this->topicRepository->getEntityManager()->createQueryBuilder()
            ->select('COUNT(DISTINCT a.id)')
            ->from(\App\Entity\Article::class, 'a')
            ->leftJoin('a.topics', 't')
            ->where('t.id IN (:topicIds)')
            ->andWhere('a.status = :status')
            ->setParameter('topicIds', $topicIds)
            ->setParameter('status', \App\Enum\ArticleStatus::PUBLISHED);
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        return $this->json([
            '@type' => 'hydra:Collection',
            'hydra:totalItems' => $total,
            'hydra:member' => array_map(fn (\App\Entity\Article $a) => [
                '@id' => '/api/articles/' . $a->getId(),
                'id' => $a->getId(),
                'title' => $a->getTitle(),
                'slug' => $a->getSlug(),
                'lead' => $a->getLead(),
                'publishedAt' => $a->getPublishedAt()?->format('c'),
            ], $articles),
        ], Response::HTTP_OK, [
            'Cache-Control' => 'public, max-age=300',
            'Vary' => 'Accept-Language',
        ]);
    }

    /**
     * Search/autocomplete topics by title.
     */
    #[Route('/search', name: 'search', methods: ['GET'], priority: 2)]
    public function search(Request $request): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));
        if ($query === '') {
            return $this->json([
                '@type' => 'hydra:Error',
                'hydra:title' => 'Invalid query',
                'hydra:description' => 'Query parameter "q" is required.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $limit = min(50, max(1, (int) $request->query->get('limit', 10)));
        $locale = $this->extractLocale($request);

        $topics = $this->topicRepository->searchByTitle($query, $locale, $limit);

        return $this->json([
            '@type' => 'hydra:Collection',
            'hydra:member' => array_map(fn (Topic $t) => $this->serializeTopic($t), $topics),
            'hydra:totalItems' => \count($topics),
        ], Response::HTTP_OK, [
            'Cache-Control' => 'public, max-age=300',
            'Vary' => 'Accept-Language',
        ]);
    }

    /**
     * Move a topic to a new parent/position.
     */
    #[Route('/{id}/move', name: 'move', methods: ['POST'], priority: 2)]
    public function move(int $id, Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_EDITOR');

        $topic = $this->topicRepository->find($id);
        if (!$topic) {
            return $this->json([
                '@type' => 'hydra:Error',
                'hydra:title' => 'Not Found',
                'hydra:description' => \sprintf('Topic with ID %d not found.', $id),
            ], Response::HTTP_NOT_FOUND);
        }

        $body = json_decode($request->getContent(), true);
        $parentId = $body['parentId'] ?? null;
        $position = (int) ($body['position'] ?? 0);

        $newParent = null;
        if ($parentId !== null) {
            $newParent = $this->topicRepository->find($parentId);
            if (!$newParent) {
                return $this->json([
                    '@type' => 'hydra:Error',
                    'hydra:title' => 'Parent not found',
                    'hydra:description' => \sprintf('Parent topic with ID %d not found.', $parentId),
                ], Response::HTTP_NOT_FOUND);
            }
        }

        $this->topicService->moveTopic($topic, $newParent, $position);
        $this->invalidateTreeCache();

        return $this->json([
            'message' => 'Topic moved successfully.',
            'topic' => $this->serializeTopic($topic),
        ]);
    }

    /**
     * Get a flat list of topics for dropdowns.
     */
    #[Route('/flat', name: 'flat', methods: ['GET'], priority: 2)]
    public function flat(Request $request): JsonResponse
    {
        $locale = $this->extractLocale($request);
        $list = $this->topicService->getFlatList($locale);

        return $this->json($list, Response::HTTP_OK, [
            'Cache-Control' => 'public, max-age=600',
            'Vary' => 'Accept-Language',
        ]);
    }

    private function extractLocale(Request $request): string
    {
        $locale = $request->query->get('locale') ?? $request->headers->get('Accept-Language', 'ro');

        if (str_contains($locale, '-')) {
            $locale = explode('-', $locale)[0];
        }
        if (str_contains($locale, ',')) {
            $locale = explode(',', $locale)[0];
        }

        return $locale;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTopic(Topic $topic): array
    {
        $path = $this->topicService->getPath($topic);
        $pathString = implode(' > ', array_map(fn (Topic $t) => $t->getTitle(), $path));

        return [
            '@id' => '/api/topics/' . $topic->getId(),
            '@type' => 'Topic',
            'id' => $topic->getId(),
            'title' => $topic->getTitle(),
            'slug' => $topic->getSlug(),
            'description' => $topic->getDescription(),
            'lvl' => $topic->getLvl(),
            'position' => $topic->getPosition(),
            'isActive' => $topic->isActive(),
            'status' => $topic->getStatus()->value,
            'isSensitive' => $topic->isSensitive(),
            'isStoryLeaf' => $topic->isStoryLeaf(),
            'path' => $pathString,
        ];
    }

    private function invalidateTreeCache(): void
    {
        $this->cache->invalidateTags(['topics_tree']);
    }
}
