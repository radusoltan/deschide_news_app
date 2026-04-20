<?php

declare(strict_types=1);

namespace App\Service\Elasticsearch;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Exception;
use RuntimeException;

/**
 * Manages Elasticsearch index lifecycle: creation, deletion, reindexing, aliases, and stats.
 *
 * Split from App\Service\ElasticService (T38.4 — SRP).
 */
class ElasticIndexManager
{
    private ?Client $client;

    private readonly string $indexPrefix;

    private array $supportedLocales = ['ro', 'en', 'ru'];

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
     * Get the Elasticsearch client.
     */
    public function getClient(): ?Client
    {
        return $this->client;
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
                        'archived_at' => ['type' => 'date'],
                        'archive_reason' => ['type' => 'keyword'],
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
                        'published_locales' => ['type' => 'keyword'],
                        'related_ids' => ['type' => 'integer'],
                        'tags' => [
                            'type' => 'nested',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'name' => [
                                    'type' => 'text',
                                    'analyzer' => 'article_analyzer',
                                    'fields' => [
                                        'keyword' => ['type' => 'keyword'],
                                    ],
                                ],
                                'slug' => ['type' => 'keyword'],
                            ],
                        ],
                        'tag_names' => [
                            'type' => 'text',
                            'analyzer' => 'article_analyzer',
                        ],
                        'topic_ids' => ['type' => 'integer'],
                        'topic_titles' => ['type' => 'keyword'],
                        'topic_slugs' => ['type' => 'keyword'],
                    ],
                ],
            ],
        ];

        try {
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
     * Create an alias pointing to an index.
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
    public function getIndexName(string $locale): string
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
