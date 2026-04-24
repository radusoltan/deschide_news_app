<?php

declare(strict_types=1);

namespace App\Service\Elasticsearch;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Exception;
use RuntimeException;
use stdClass;

/**
 * Handles article search and suggestion queries against Elasticsearch.
 *
 * Split from App\Service\ElasticService (T38.4 — SRP).
 */
class ArticleSearchService
{
    private ?Client $client;

    private readonly string $indexPrefix;

    private readonly bool $enabled;

    public function __construct(
        string $elasticsearchHost,
        string $elasticsearchUser = '',
        string $elasticsearchPassword = '',
        bool $elasticsearchVerifySsl = true,
        string $elasticsearchIndexPrefix = 'deschide',
    ) {
        $this->indexPrefix = $elasticsearchIndexPrefix . '_articles';
        $this->enabled = '' !== $elasticsearchHost && '0' !== $elasticsearchHost;

        if ($this->enabled) {
            $builder = ClientBuilder::create()
                ->setHosts([$elasticsearchHost])
                ->setSSLVerification($elasticsearchVerifySsl);

            if ($elasticsearchUser && $elasticsearchPassword) {
                $builder->setBasicAuthentication($elasticsearchUser, $elasticsearchPassword);
            }

            $this->client = $builder->build();
        } else {
            $this->client = null;
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Search for articles in a specific locale index.
     */
    public function search(string $query, int $from = 0, int $size = 20, array $filters = [], array $sort = [], string $locale = 'ro'): array
    {
        if (!$this->enabled) {
            return ['hits' => ['hits' => [], 'total' => ['value' => 0]]];
        }

        $indexName = $this->getIndexName($locale);
        $must = [];

        // Add search query if provided
        if ('' !== $query && '0' !== $query) {
            $must[] = [
                'multi_match' => [
                    'query' => $query,
                    'fields' => ['title^3', 'tag_names^2.5', 'lead^2', 'content'],
                    'type' => 'best_fields',
                    'fuzziness' => 'AUTO',
                ],
            ];
        }

        // Add filters
        if (!empty($filters['status'])) {
            $must[] = ['term' => ['status' => $filters['status']]];
        }

        if (!empty($filters['category_id'])) {
            $must[] = ['term' => ['category.id' => $filters['category_id']]];
        }

        if (!empty($filters['is_featured'])) {
            $must[] = ['term' => ['is_featured' => $filters['is_featured']]];
        }

        if (!empty($filters['badge'])) {
            $must[] = ['term' => ['badge' => $filters['badge']]];
        }

        // Add filter for tags
        if (!empty($filters['tag_ids'])) {
            $tagIds = \is_array($filters['tag_ids']) ? $filters['tag_ids'] : [$filters['tag_ids']];

            $must[] = [
                'nested' => [
                    'path' => 'tags',
                    'query' => [
                        'terms' => [
                            'tags.id' => $tagIds,
                        ],
                    ],
                ],
            ];
        }

        // Build sort array
        $esSort = $this->buildSortArray($sort, $query);

        // Build base query
        $baseQuery = [] === $must ? ['match_all' => new stdClass()] : ['bool' => ['must' => $must]];

        // Wrap in function_score for featured articles boost
        $queryBody = [
            'function_score' => [
                'query' => $baseQuery,
                'functions' => [
                    [
                        'filter' => ['term' => ['is_featured' => true]],
                        'weight' => 1.5,
                    ],
                ],
                'score_mode' => 'multiply',
                'boost_mode' => 'multiply',
            ],
        ];

        $params = [
            'index' => $indexName,
            'body' => [
                'from' => $from,
                'size' => $size,
                'query' => $queryBody,
                'highlight' => [
                    'fields' => [
                        'title' => new stdClass(),
                        'lead' => [
                            'fragment_size' => 150,
                            'number_of_fragments' => 2,
                        ],
                        'content' => [
                            'fragment_size' => 150,
                            'number_of_fragments' => 3,
                        ],
                    ],
                ],
                'sort' => $esSort,
            ],
        ];

        try {
            $response = $this->client->search($params);

            return $response->asArray();
        } catch (Exception $e) {
            throw new RuntimeException('Search failed: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Search for archived articles in a specific locale index.
     */
    public function searchArchivedArticles(
        string $query = '',
        int $from = 0,
        int $size = 20,
        array $filters = [],
        array $sort = [],
        string $locale = 'ro'
    ): array {
        if (!$this->enabled) {
            return ['hits' => ['hits' => [], 'total' => ['value' => 0]]];
        }

        // Force archived status filter
        $filters['status'] = 'archived';

        // Default sort for archives: most recently archived first
        if (empty($sort)) {
            $sort = ['archivedAt' => 'desc'];
        }

        $indexName = $this->getIndexName($locale);
        $must = [];

        // Add archived status filter
        $must[] = ['term' => ['status' => 'archived']];

        // Add search query if provided
        if ('' !== $query && '0' !== $query) {
            $must[] = [
                'multi_match' => [
                    'query' => $query,
                    'fields' => ['title^3', 'tag_names^2.5', 'lead^2', 'content'],
                    'type' => 'best_fields',
                    'fuzziness' => 'AUTO',
                ],
            ];
        }

        // Add archive reason filter
        if (!empty($filters['archive_reason'])) {
            $must[] = ['term' => ['archive_reason' => $filters['archive_reason']]];
        }

        // Add category filter
        if (!empty($filters['category_id'])) {
            $must[] = ['term' => ['category.id' => $filters['category_id']]];
        }

        // Add date range filter for archived_at
        if (!empty($filters['archived_from']) || !empty($filters['archived_to'])) {
            $rangeFilter = ['range' => ['archived_at' => []]];
            if (!empty($filters['archived_from'])) {
                $rangeFilter['range']['archived_at']['gte'] = $filters['archived_from'];
            }
            if (!empty($filters['archived_to'])) {
                $rangeFilter['range']['archived_at']['lte'] = $filters['archived_to'];
            }
            $must[] = $rangeFilter;
        }

        // Build sort array
        $esSort = $this->buildSortArray($sort, $query, 'archived_at');

        $params = [
            'index' => $indexName,
            'body' => [
                'from' => $from,
                'size' => $size,
                'query' => ['bool' => ['must' => $must]],
                'highlight' => [
                    'fields' => [
                        'title' => new stdClass(),
                        'lead' => [
                            'fragment_size' => 150,
                            'number_of_fragments' => 2,
                        ],
                        'content' => [
                            'fragment_size' => 150,
                            'number_of_fragments' => 3,
                        ],
                    ],
                ],
                'sort' => $esSort,
            ],
        ];

        try {
            $response = $this->client->search($params);

            return $response->asArray();
        } catch (Exception $e) {
            throw new RuntimeException('Archived articles search failed: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Get autocomplete suggestions for a given prefix.
     */
    public function suggest(string $prefix, int $size = 10, string $locale = 'ro'): array
    {
        if (!$this->enabled) {
            return [];
        }

        $indexName = $this->getIndexName($locale);

        $params = [
            'index' => $indexName,
            'body' => [
                'suggest' => [
                    'article-suggest' => [
                        'prefix' => $prefix,
                        'completion' => [
                            'field' => 'suggest',
                            'size' => $size,
                            'skip_duplicates' => true,
                            'fuzzy' => [
                                'fuzziness' => 'AUTO',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        try {
            $response = $this->client->search($params);
            $result = $response->asArray();

            $suggestions = [];
            if (isset($result['suggest']['article-suggest'][0]['options'])) {
                foreach ($result['suggest']['article-suggest'][0]['options'] as $option) {
                    $suggestions[] = [
                        'text' => $option['text'],
                        'score' => $option['_score'],
                        'source' => $option['_source'] ?? null,
                    ];
                }
            }

            return $suggestions;
        } catch (Exception $e) {
            throw new RuntimeException('Suggest failed: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Get index name for a specific locale.
     */
    private function getIndexName(string $locale): string
    {
        return $this->indexPrefix . '_' . $locale;
    }

    /**
     * Build Elasticsearch sort array from field map.
     */
    private function buildSortArray(array $sort, string $query, string $defaultSortField = 'created_at'): array
    {
        $esSort = [];
        if ([] !== $sort) {
            foreach ($sort as $field => $direction) {
                $esField = match ($field) {
                    'archivedAt' => 'archived_at',
                    'publishedAt' => 'published_at',
                    'createdAt' => 'created_at',
                    'viewCount' => 'view_count',
                    'title' => 'title.keyword',
                    '_score' => '_score',
                    default => $field,
                };
                $esSort[] = [$esField => ['order' => $direction]];
            }
        } else {
            if ('' !== $query && '0' !== $query) {
                $esSort[] = ['_score' => ['order' => 'desc']];
            }
            $esSort[] = [$defaultSortField => ['order' => 'desc']];
        }

        return $esSort;
    }
}
