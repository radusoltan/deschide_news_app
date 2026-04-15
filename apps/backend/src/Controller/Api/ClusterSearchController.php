<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Repository\StoryClusterRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/story-clusters')]
#[IsGranted('ROLE_EDITOR')]
final class ClusterSearchController extends AbstractController
{
    public function __construct(
        private readonly StoryClusterRepository $repository,
    ) {}

    #[Route('/search', name: 'api_cluster_search', methods: ['GET'], priority: 10)]
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim($request->query->getString('q', ''));
        $limit = min(50, max(1, $request->query->getInt('limit', 20)));

        if ($query === '') {
            return $this->json(['items' => [], 'totalItems' => 0]);
        }

        $results = $this->repository->searchByText($query, $limit);

        $items = array_map(function (array $row) {
            $cluster = $row['cluster'];
            $topics = [];
            foreach ($cluster->getTopics() as $topic) {
                $topics[] = ['id' => $topic->getId(), 'title' => $topic->getTitle()];
            }

            return [
                'id' => $cluster->getId(),
                'primaryHeadline' => $cluster->getPrimaryHeadline(),
                'summaryShort' => $cluster->getSummaryShort(),
                'importanceScore' => $cluster->getImportanceScore(),
                'sourceCount' => $cluster->getSourceCount(),
                'articleCount' => $cluster->getArticleCount(),
                'status' => $cluster->getStatus()->value,
                'firstSeenAt' => $cluster->getFirstSeenAt()->format('c'),
                'lastUpdatedAt' => $cluster->getLastUpdatedAt()->format('c'),
                'promotedToPressRelease' => $cluster->isPromotedToPressRelease(),
                'editorialBoost' => $cluster->getEditorialBoost(),
                'topics' => $topics,
                'rank' => $row['rank'],
            ];
        }, $results);

        return $this->json([
            'items' => $items,
            'totalItems' => \count($items),
        ]);
    }
}
