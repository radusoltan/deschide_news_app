<?php

declare(strict_types=1);

namespace App\Service;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Exception;

class ImageElasticService
{
    private ?Client $client;

    private string $indexName = 'deschide_images';

    private readonly bool $enabled;

    public function __construct(
        string $elasticsearchHost,
        string $elasticsearchUser = '',
        string $elasticsearchPassword = '',
        bool $elasticsearchVerifySsl = true
    ) {
        $this->enabled = '' !== $elasticsearchHost && '0' !== $elasticsearchHost;

        if ($this->enabled) {
            $builder = ClientBuilder::create()
                ->setHosts([$elasticsearchHost])
                ->setSSLVerification($elasticsearchVerifySsl);

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
     * Create the images index with mappings and settings.
     */
    public function createIndex(): void
    {
        if (!$this->enabled) {
            return;
        }

        // Delete existing index if it exists
        if ($this->client->indices()->exists(['index' => $this->indexName])->asBool()) {
            $this->client->indices()->delete(['index' => $this->indexName]);
        }

        $params = [
            'index' => $this->indexName,
            'body' => [
                'settings' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 0,
                    'analysis' => [
                        'analyzer' => [
                            'image_analyzer' => [
                                'type' => 'custom',
                                'tokenizer' => 'standard',
                                'filter' => ['lowercase', 'asciifolding'],
                            ],
                        ],
                    ],
                ],
                'mappings' => [
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'filename' => [
                            'type' => 'text',
                            'analyzer' => 'image_analyzer',
                            'fields' => [
                                'keyword' => ['type' => 'keyword'],
                            ],
                        ],
                        'originalFilename' => [
                            'type' => 'text',
                            'analyzer' => 'image_analyzer',
                            'fields' => [
                                'keyword' => ['type' => 'keyword'],
                            ],
                        ],
                        'alt' => [
                            'type' => 'text',
                            'analyzer' => 'image_analyzer',
                        ],
                        'caption' => [
                            'type' => 'text',
                            'analyzer' => 'image_analyzer',
                        ],
                        'description' => [
                            'type' => 'text',
                            'analyzer' => 'image_analyzer',
                        ],
                        'mimeType' => ['type' => 'keyword'],
                        'size' => ['type' => 'long'],
                        'width' => ['type' => 'integer'],
                        'height' => ['type' => 'integer'],
                        'uploadedAt' => ['type' => 'date'],
                        // Completion suggester for autocomplete
                        'suggest' => [
                            'type' => 'completion',
                            'analyzer' => 'simple',
                        ],
                    ],
                ],
            ],
        ];

        $this->client->indices()->create($params);
    }

    /**
     * Index a single image document.
     */
    public function indexDocument(array $document): void
    {
        if (!$this->enabled) {
            return;
        }

        $params = [
            'index' => $this->indexName,
            'id' => $document['id'],
            'body' => $document,
        ];

        $this->client->index($params);
    }

    /**
     * Search images with multi-field search.
     */
    public function search(string $query, int $size = 100): array
    {
        if (!$this->enabled) {
            return [];
        }

        $params = [
            'index' => $this->indexName,
            'body' => [
                'size' => $size,
                'query' => [
                    'multi_match' => [
                        'query' => $query,
                        'fields' => [
                            'originalFilename^3',  // Boost original filename
                            'filename^2',          // Boost filename
                            'alt^2',               // Boost alt text
                            'caption',
                            'description',
                        ],
                        'type' => 'best_fields',
                        'fuzziness' => 'AUTO',
                    ],
                ],
                'sort' => [
                    '_score' => ['order' => 'desc'],
                    'uploadedAt' => ['order' => 'desc'],
                ],
            ],
        ];

        $response = $this->client->search($params);

        $hits = $response['hits']['hits'] ?? [];
        $results = [];

        foreach ($hits as $hit) {
            $results[] = [
                'id' => $hit['_source']['id'],
                'score' => $hit['_score'],
            ];
        }

        return $results;
    }

    /**
     * Autocomplete suggestions for images.
     */
    public function suggest(string $prefix, int $size = 10): array
    {
        if (!$this->enabled) {
            return [];
        }

        $params = [
            'index' => $this->indexName,
            'body' => [
                'suggest' => [
                    'image-suggest' => [
                        'prefix' => $prefix,
                        'completion' => [
                            'field' => 'suggest',
                            'size' => $size,
                            'skip_duplicates' => true,
                        ],
                    ],
                ],
            ],
        ];

        $response = $this->client->search($params);

        $suggestions = $response['suggest']['image-suggest'][0]['options'] ?? [];
        $results = [];

        foreach ($suggestions as $suggestion) {
            $results[] = $suggestion['text'];
        }

        return $results;
    }

    /**
     * Delete an image document from the index.
     */
    public function deleteDocument(int $id): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            $this->client->delete([
                'index' => $this->indexName,
                'id' => $id,
            ]);
        } catch (Exception $e) {
            // Document might not exist, ignore
        }
    }

    /**
     * Get cluster health.
     */
    public function getHealth(): array
    {
        if (!$this->enabled) {
            return ['status' => 'disabled'];
        }

        $health = $this->client->cluster()->health();

        return [
            'status' => $health['status'],
            'cluster_name' => $health['cluster_name'],
            'number_of_nodes' => $health['number_of_nodes'],
        ];
    }
}
