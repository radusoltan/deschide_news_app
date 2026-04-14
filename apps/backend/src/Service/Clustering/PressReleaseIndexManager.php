<?php

declare(strict_types=1);

namespace App\Service\Clustering;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Psr\Log\LoggerInterface;

/**
 * Manages the Elasticsearch index for PressReleases used by the clustering pipeline.
 *
 * Index: deschide_press_releases
 * Purpose: MLT (More Like This) similarity matching to group PressReleases into StoryClusters.
 */
class PressReleaseIndexManager
{
    public const INDEX_NAME = 'deschide_press_releases';

    /**
     * High-frequency, non-discriminative terms for Moldovan news context.
     * These words appear in nearly every article and provide no clustering signal.
     */
    public const CONTEXT_STOP_WORDS = [
        // RO contextual
        'moldova', 'republica', 'chișinău', 'chisinau', 'moldovei',
        'guvernul', 'autoritățile', 'autoritatile', 'țara', 'tara',
        'milioane', 'lei', 'mln',
        // EN contextual
        'republic', 'government',
        // RU contextual
        'молдова', 'республика', 'кишинев', 'правительство',
        // Common noise
        'www', 'http', 'https', 'foto', 'video', 'sursa', 'source',
    ];

    private ?Client $client;
    private readonly bool $enabled;

    public function __construct(
        string $elasticsearchHost,
        string $elasticsearchUser = '',
        string $elasticsearchPassword = '',
        bool $elasticsearchVerifySsl = true,
        private readonly LoggerInterface $logger = new \Psr\Log\NullLogger(),
    ) {
        $this->enabled = $elasticsearchHost !== '' && $elasticsearchHost !== '0';

        if ($this->enabled) {
            $builder = ClientBuilder::create()
                ->setHosts([$elasticsearchHost])
                ->setSSLVerification($elasticsearchVerifySsl);

            if ($elasticsearchUser !== '' && $elasticsearchPassword !== '') {
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

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function getIndexName(): string
    {
        return self::INDEX_NAME;
    }

    public function createIndex(bool $deleteIfExists = false): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            $exists = $this->client->indices()->exists(['index' => self::INDEX_NAME])->asBool();

            if ($exists && $deleteIfExists) {
                $this->client->indices()->delete(['index' => self::INDEX_NAME]);
                $this->logger->info('PressReleaseIndexManager: deleted existing index');
            } elseif ($exists) {
                $this->logger->info('PressReleaseIndexManager: index already exists');
                return;
            }
        } catch (\Throwable $e) {
            $this->logger->error('PressReleaseIndexManager: error checking index', [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            $this->client->indices()->create([
                'index' => self::INDEX_NAME,
                'body' => [
                    'settings' => $this->getSettings(),
                    'mappings' => $this->getMappings(),
                ],
            ]);
            $this->logger->info('PressReleaseIndexManager: index created');
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to create press_releases index: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Index a single PressRelease document.
     */
    public function indexDocument(int $pressReleaseId, array $document): void
    {
        if (!$this->enabled) {
            return;
        }

        try {
            $this->client->index([
                'index' => self::INDEX_NAME,
                'id' => (string) $pressReleaseId,
                'body' => $document,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('PressReleaseIndexManager: failed to index PR', [
                'pressReleaseId' => $pressReleaseId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Bulk index multiple PressRelease documents.
     *
     * @param array<int, array> $documents Map of pressReleaseId => document body
     */
    public function bulkIndex(array $documents): int
    {
        if (!$this->enabled || $documents === []) {
            return 0;
        }

        $body = [];
        foreach ($documents as $id => $doc) {
            $body[] = ['index' => ['_index' => self::INDEX_NAME, '_id' => (string) $id]];
            $body[] = $doc;
        }

        try {
            $response = $this->client->bulk(['body' => $body]);
            $result = $response->asArray();

            $errors = 0;
            if ($result['errors'] ?? false) {
                foreach ($result['items'] ?? [] as $item) {
                    if (isset($item['index']['error'])) {
                        $errors++;
                        $this->logger->warning('PressReleaseIndexManager: bulk item error', [
                            'error' => $item['index']['error']['reason'] ?? 'unknown',
                        ]);
                    }
                }
            }

            return \count($documents) - $errors;
        } catch (\Throwable $e) {
            $this->logger->error('PressReleaseIndexManager: bulk index failed', [
                'error' => $e->getMessage(),
                'count' => \count($documents),
            ]);
            return 0;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettings(): array
    {
        return [
            'number_of_shards' => 1,
            'number_of_replicas' => 0,
            'analysis' => [
                'analyzer' => [
                    'multilingual_standard' => [
                        'type' => 'custom',
                        'tokenizer' => 'standard',
                        'filter' => [
                            'lowercase',
                            'asciifolding',
                            'ro_stop',
                            'en_stop',
                            'ru_stop',
                            'context_stop',
                        ],
                    ],
                ],
                'filter' => [
                    'ro_stop' => [
                        'type' => 'stop',
                        'stopwords' => '_romanian_',
                    ],
                    'en_stop' => [
                        'type' => 'stop',
                        'stopwords' => '_english_',
                    ],
                    'ru_stop' => [
                        'type' => 'stop',
                        'stopwords' => '_russian_',
                    ],
                    'context_stop' => [
                        'type' => 'stop',
                        'stopwords' => self::CONTEXT_STOP_WORDS,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getMappings(): array
    {
        return [
            'properties' => [
                'press_release_id' => ['type' => 'integer'],
                'title' => [
                    'type' => 'text',
                    'analyzer' => 'multilingual_standard',
                ],
                'content' => [
                    'type' => 'text',
                    'analyzer' => 'multilingual_standard',
                ],
                'lead' => [
                    'type' => 'text',
                    'analyzer' => 'multilingual_standard',
                ],
                'source_name' => ['type' => 'keyword'],
                'source_hostname' => ['type' => 'keyword'],
                'source_type' => ['type' => 'keyword'],
                'category_slug' => ['type' => 'keyword'],
                'detected_language' => ['type' => 'keyword'],
                'original_language' => ['type' => 'keyword'],
                'created_at' => ['type' => 'date'],
                'received_at' => ['type' => 'date'],
            ],
        ];
    }
}
