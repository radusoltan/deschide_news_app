<?php

declare(strict_types=1);

namespace App\Service;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Exception;
use RuntimeException;
use stdClass;

class ElasticService
{
    private ?Client $client;

    private string $indexPrefix = 'deschide_articles';

    private array $supportedLocales = ['ro', 'en', 'ru'];

    private readonly bool $enabled;

    public function __construct(
        string $elasticsearchHost,
        string $elasticsearchUser = '',
        string $elasticsearchPassword = ''
    ) {
        $this->enabled = '' !== $elasticsearchHost && '0' !== $elasticsearchHost;

        if ($this->enabled) {
            $builder = ClientBuilder::create()
                ->setHosts([$elasticsearchHost])
                ->setSSLVerification(false);

            // Add authentication if credentials provided
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
     * Create the articles index with mappings and settings for a specific locale.
     */
    public function createIndex(string $locale = 'ro'): void
    {
        if (!$this->enabled) {
            return;
        }

        $indexName = $this->getIndexName($locale);

        // Get locale-specific analyzer
        $analyzer = $this->getAnalyzerForLocale($locale);

        $params = [
            'index' => $indexName,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                    'analysis' => $analyzer,
                ],
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'title' => [
                            'type' => 'text',
                            'analyzer' => 'article_analyzer',
                            'fields' => [
                                'keyword' => ['type' => 'keyword'],
                            ],
                        ],
                        'slug' => ['type' => 'keyword'],
                        'lead' => [
                            'type' => 'text',
                            'analyzer' => 'article_analyzer',
                        ],
                        'content' => [
                            'type' => 'text',
                            'analyzer' => 'article_analyzer',
                        ],
                        // Completion suggester for autocomplete
                        'suggest' => [
                            'type' => 'completion',
                            'analyzer' => 'simple',
                            'preserve_separators' => true,
                            'preserve_position_increments' => true,
                            'max_input_length' => 50,
                        ],
                        'locale' => ['type' => 'keyword'],
                        'view_count' => ['type' => 'integer'],
                        'published_at' => ['type' => 'date'],
                        'publish_at' => ['type' => 'date'],
                        'created_at' => ['type' => 'date'],
                        'authors' => [
                            'type' => 'nested',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'name' => ['type' => 'text'],
                            ],
                        ],
                        'category' => [
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'name' => ['type' => 'text'],
                                'slug' => ['type' => 'keyword'],
                            ],
                        ],
                        'badge' => ['type' => 'keyword'],
                        'is_featured' => ['type' => 'boolean'],
                        'status' => ['type' => 'keyword'],
                        'related_ids' => ['type' => 'integer'],
                    ],
                ],
            ],
        ];

        try {
            // Check if index exists
            if ($this->client->indices()->exists(['index' => $indexName])->asBool()) {
                return;
            }

            $this->client->indices()->create($params);
        } catch (Exception $e) {
            throw new RuntimeException('Failed to create Elasticsearch index: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Create indices for all supported locales.
     */
    public function createAllIndices(): void
    {
        foreach ($this->supportedLocales as $locale) {
            $this->createIndex($locale);
        }
    }

    /**
     * Index a document (article) in the appropriate locale index.
     */
    public function indexDocument(array $document, string $locale = 'ro'): void
    {
        if (!$this->enabled) {
            return;
        }

        $indexName = $this->getIndexName($locale);

        $params = [
            'index' => $indexName,
            'id' => $document['id'],
            'body' => $document,
        ];

        try {
            $this->client->index($params);
        } catch (Exception $e) {
            throw new RuntimeException('Failed to index document: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Delete a document from all locale indices.
     */
    public function deleteDocument(int $id): void
    {
        if (!$this->enabled) {
            return;
        }

        // Delete from all locale indices
        foreach ($this->supportedLocales as $locale) {
            $indexName = $this->getIndexName($locale);

            $params = [
                'index' => $indexName,
                'id' => $id,
            ];

            try {
                $this->client->delete($params);
            } catch (Exception $e) {
                // Ignore if document doesn't exist
                if (!str_contains($e->getMessage(), 'not_found')) {
                    error_log("Failed to delete document {$id} from {$indexName}: " . $e->getMessage());
                }
            }
        }
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
                    'fields' => ['title^3', 'lead^2', 'content'],
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

        // Build sort array
        $esSort = [];
        if ([] !== $sort) {
            foreach ($sort as $field => $direction) {
                $esField = match ($field) {
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
            // Default sorting
            if ('' !== $query && '0' !== $query) {
                $esSort[] = ['_score' => ['order' => 'desc']];
            }
            $esSort[] = ['created_at' => ['order' => 'desc']];
        }

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

            // Extract suggestions from response
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
     * Get the Elasticsearch client.
     */
    public function getClient(): ?Client
    {
        return $this->client;
    }

    /**
     * Get Elasticsearch cluster health.
     */
    public function getClusterHealth(): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        try {
            $response = $this->client->cluster()->health();

            return $response->asArray();
        } catch (Exception) {
            return null;
        }
    }

    /**
     * Bulk index multiple documents for better performance.
     *
     * @param array $documents Array of documents to index
     * @param string $locale Target locale
     *
     * @return array Stats about bulk operation (indexed, errors)
     */
    public function bulkIndexDocuments(array $documents, string $locale = 'ro'): array
    {
        if (!$this->enabled || empty($documents)) {
            return ['indexed' => 0, 'errors' => 0];
        }

        $indexName = $this->getIndexName($locale);
        $params = ['body' => []];

        foreach ($documents as $document) {
            // Add index action
            $params['body'][] = [
                'index' => [
                    '_index' => $indexName,
                    '_id' => $document['id'],
                ],
            ];

            // Add document body
            $params['body'][] = $document;
        }

        try {
            $response = $this->client->bulk($params);
            $result = $response->asArray();

            $indexed = 0;
            $errors = 0;

            if (isset($result['items'])) {
                foreach ($result['items'] as $item) {
                    if (isset($item['index']['error'])) {
                        ++$errors;
                    } else {
                        ++$indexed;
                    }
                }
            }

            return [
                'indexed' => $indexed,
                'errors' => $errors,
                'took' => $result['took'] ?? 0,
            ];
        } catch (Exception $e) {
            throw new RuntimeException('Bulk indexing failed: ' . $e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Create an alias pointing to an index.
     *
     * @param string $aliasName Alias name
     * @param string $indexName Index name
     */
    public function createAlias(string $aliasName, string $indexName): void
    {
        if (!$this->enabled) {
            return;
        }

        $params = [
            'index' => $indexName,
            'name' => $aliasName,
        ];

        try {
            $this->client->indices()->putAlias($params);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to create alias: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    /**
     * Atomic alias swap for zero-downtime reindex.
     *
     * @param string $aliasName Alias name
     * @param string $oldIndexName Old index to remove from alias
     * @param string $newIndexName New index to add to alias
     */
    public function swapAlias(string $aliasName, string $oldIndexName, string $newIndexName): void
    {
        if (!$this->enabled) {
            return;
        }

        $params = [
            'body' => [
                'actions' => [
                    [
                        'remove' => [
                            'index' => $oldIndexName,
                            'alias' => $aliasName,
                        ],
                    ],
                    [
                        'add' => [
                            'index' => $newIndexName,
                            'alias' => $aliasName,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $this->client->indices()->updateAliases($params);
        } catch (Exception $e) {
            throw new RuntimeException("Failed to swap alias: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    /**
     * Get all aliases for an index or all indices.
     */
    public function getAliases(?string $indexName = null): array
    {
        if (!$this->enabled) {
            return [];
        }

        $params = $indexName ? ['index' => $indexName] : [];

        try {
            $response = $this->client->indices()->getAlias($params);

            return $response->asArray();
        } catch (Exception) {
            return [];
        }
    }

    /**
     * Delete an index.
     */
    public function deleteIndex(string $indexName): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            if ($this->client->indices()->exists(['index' => $indexName])->asBool()) {
                $this->client->indices()->delete(['index' => $indexName]);
            }
        } catch (Exception $e) {
            throw new RuntimeException("Failed to delete index: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    /**
     * Reindex all documents from old index to new index.
     *
     * @param string $sourceIndex Source index name
     * @param string $destIndex Destination index name
     *
     * @return array Reindex statistics
     */
    public function reindex(string $sourceIndex, string $destIndex): array
    {
        if (!$this->enabled) {
            return ['total' => 0, 'created' => 0];
        }

        $params = [
            'body' => [
                'source' => [
                    'index' => $sourceIndex,
                ],
                'dest' => [
                    'index' => $destIndex,
                ],
            ],
        ];

        try {
            $response = $this->client->reindex($params);

            return $response->asArray();
        } catch (Exception $e) {
            throw new RuntimeException("Reindex failed: {$e->getMessage()}", $e->getCode(), $e);
        }
    }

    /**
     * Get index statistics.
     */
    public function getIndexStats(string $indexName): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        try {
            $response = $this->client->indices()->stats(['index' => $indexName]);

            return $response->asArray();
        } catch (Exception) {
            return null;
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
     * Get analyzer configuration for a specific locale.
     */
    private function getAnalyzerForLocale(string $locale): array
    {
        return match ($locale) {
            'en' => [
                'analyzer' => [
                    'article_analyzer' => [
                        'type' => 'english',
                    ],
                ],
            ],
            'ro' => [
                'analyzer' => [
                    'article_analyzer' => [
                        'type' => 'romanian',
                    ],
                ],
            ],
            'ru' => [
                'analyzer' => [
                    'article_analyzer' => [
                        'type' => 'russian',
                    ],
                ],
            ],
            default => [
                'analyzer' => [
                    'article_analyzer' => [
                        'type' => 'standard',
                        'filter' => ['lowercase', 'asciifolding'],
                    ],
                ],
            ],
        };
    }
}
