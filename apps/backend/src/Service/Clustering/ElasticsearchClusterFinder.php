<?php

declare(strict_types=1);

namespace App\Service\Clustering;

use Psr\Log\LoggerInterface;

/**
 * Finds similar PressReleases using Elasticsearch More Like This query.
 * Used by ClusteringService to group articles about the same event.
 *
 * Queries the deschide_press_releases index (NOT the articles index).
 */
class ElasticsearchClusterFinder
{
    public function __construct(
        private readonly PressReleaseIndexManager $indexManager,
        private readonly LoggerInterface $logger = new \Psr\Log\NullLogger(),
    ) {}

    /**
     * Find PressReleases similar to the given title+content using MLT query.
     *
     * @param int|null $excludeId PressRelease ID to exclude from results (self-match prevention)
     * @return list<array{score: float, pressReleaseId: int, title: string}>
     */
    public function findSimilar(string $title, string $content, float $minScore = 0.40, ?int $excludeId = null): array
    {
        if (!$this->indexManager->isEnabled() || $this->indexManager->getClient() === null) {
            return [];
        }

        $indexName = $this->indexManager->getIndexName();

        try {
            $query = [
                'bool' => [
                    'must' => [
                        [
                            'more_like_this' => [
                                'fields' => ['title', 'content', 'lead'],
                                'like' => [
                                    [
                                        '_index' => $indexName,
                                        'doc' => [
                                            'title' => $title,
                                            'content' => mb_substr($content, 0, 5000),
                                        ],
                                    ],
                                ],
                                'min_term_freq' => 1,
                                'min_doc_freq' => 1,
                                'minimum_should_match' => '25%',
                                'max_query_terms' => 30,
                            ],
                        ],
                    ],
                ],
            ];

            // Exclude the source PressRelease from results
            if ($excludeId !== null) {
                $query['bool']['must_not'] = [
                    ['term' => ['press_release_id' => $excludeId]],
                ];
            }

            $response = $this->indexManager->getClient()->search([
                'index' => $indexName,
                'body' => [
                    'query' => $query,
                    'min_score' => $minScore,
                    'size' => 10,
                    '_source' => ['title', 'press_release_id'],
                ],
            ]);

            $results = [];
            $hits = $response->asArray()['hits']['hits'] ?? [];

            foreach ($hits as $hit) {
                $results[] = [
                    'score' => (float) $hit['_score'],
                    'pressReleaseId' => (int) ($hit['_source']['press_release_id'] ?? $hit['_id']),
                    'title' => $hit['_source']['title'] ?? '',
                ];
            }

            $this->logger->debug('ElasticsearchClusterFinder: found {count} similar PRs', [
                'count' => \count($results),
                'queryTitle' => mb_substr($title, 0, 80),
                'minScore' => $minScore,
            ]);

            return $results;
        } catch (\Throwable $e) {
            $this->logger->error('ElasticsearchClusterFinder: search failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function isEnabled(): bool
    {
        return $this->indexManager->isEnabled();
    }
}
