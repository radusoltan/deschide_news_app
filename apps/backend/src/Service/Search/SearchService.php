<?php

declare(strict_types=1);

namespace App\Service\Search;

use Psr\Log\LoggerInterface;

readonly class SearchService
{
    private const DEFAULT_PAGE_SIZE = 20;

    public function __construct(
        private ElasticsearchIndexManager $indexManager,
        private LoggerInterface $logger,
    ) {}

    /**
     * Search articles in a specific locale.
     *
     * @return array{
     *     total: int,
     *     hits: list<array{id: int, score: float, highlight: array<string, list<string>>, source: array<string, mixed>}>,
     * }
     */
    public function search(string $query, string $locale = 'ro', int $page = 1, int $size = self::DEFAULT_PAGE_SIZE): array
    {
        if (!$this->indexManager->isEnabled()) {
            return ['total' => 0, 'hits' => []];
        }

        $fields = $this->getFieldsForLocale($locale);

        $body = [
            'query' => [
                'multi_match' => [
                    'query' => $query,
                    'fields' => $fields,
                    'type' => 'best_fields',
                    'fuzziness' => 'AUTO',
                ],
            ],
            'highlight' => [
                'fields' => [
                    "title_{$locale}" => new \stdClass(),
                    "description_{$locale}" => new \stdClass(),
                    "body_{$locale}" => ['fragment_size' => 150, 'number_of_fragments' => 2],
                ],
                'pre_tags' => ['<mark>'],
                'post_tags' => ['</mark>'],
            ],
            'from' => ($page - 1) * $size,
            'size' => $size,
            'sort' => [
                '_score',
                ['date_published' => ['order' => 'desc', 'missing' => '_last']],
            ],
        ];

        try {
            $response = $this->indexManager->getClient()->search([
                'index' => $this->indexManager->getIndexName(),
                'body' => $body,
            ]);

            $data = $response->asArray();

            return $this->formatResults($data);
        } catch (\Throwable $e) {
            $this->logger->error('SearchService: search failed', [
                'query' => $query,
                'locale' => $locale,
                'error' => $e->getMessage(),
            ]);

            return ['total' => 0, 'hits' => []];
        }
    }

    /**
     * @return list<string>
     */
    private function getFieldsForLocale(string $locale): array
    {
        return [
            "title_{$locale}^3",
            "description_{$locale}^2",
            "body_{$locale}",
        ];
    }

    /**
     * @return array{total: int, hits: list<array<string, mixed>>}
     */
    private function formatResults(array $data): array
    {
        $total = $data['hits']['total']['value'] ?? 0;
        $hits = [];

        foreach ($data['hits']['hits'] ?? [] as $hit) {
            $hits[] = [
                'id' => (int) ($hit['_source']['article_id'] ?? $hit['_id']),
                'score' => (float) $hit['_score'],
                'highlight' => $hit['highlight'] ?? [],
                'source' => $hit['_source'] ?? [],
            ];
        }

        return ['total' => $total, 'hits' => $hits];
    }
}
