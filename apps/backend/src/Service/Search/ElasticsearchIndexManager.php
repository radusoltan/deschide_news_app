<?php

declare(strict_types=1);

namespace App\Service\Search;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Psr\Log\LoggerInterface;

class ElasticsearchIndexManager
{
    private const INDEX_NAME = 'deschide_articles_trilingual';

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

    /**
     * Create the unified trilingual index with language-specific analyzers.
     */
    public function createIndex(bool $deleteIfExists = false): void
    {
        if (!$this->enabled) {
            return;
        }

        $indexName = self::INDEX_NAME;

        try {
            $exists = $this->client->indices()->exists(['index' => $indexName])->asBool();

            if ($exists && $deleteIfExists) {
                $this->client->indices()->delete(['index' => $indexName]);
                $this->logger->info('ElasticsearchIndexManager: deleted existing index', [
                    'index' => $indexName,
                ]);
            } elseif ($exists) {
                $this->logger->info('ElasticsearchIndexManager: index already exists', [
                    'index' => $indexName,
                ]);

                return;
            }
        } catch (\Throwable $e) {
            $this->logger->error('ElasticsearchIndexManager: error checking index', [
                'error' => $e->getMessage(),
            ]);
        }

        $params = [
            'index' => $indexName,
            'body' => [
                'settings' => $this->getSettings(),
                'mappings' => $this->getMappings(),
            ],
        ];

        try {
            $this->client->indices()->create($params);
            $this->logger->info('ElasticsearchIndexManager: index created', [
                'index' => $indexName,
            ]);
        } catch (\Throwable $e) {
            throw new \RuntimeException('Failed to create trilingual index: ' . $e->getMessage(), 0, $e);
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
                    'romanian_custom' => [
                        'type' => 'custom',
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'romanian_stop', 'romanian_stemmer'],
                    ],
                    'romanian_fuzzy' => [
                        'type' => 'custom',
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'asciifolding', 'romanian_synonyms', 'romanian_stop', 'romanian_stemmer'],
                    ],
                    'russian_custom' => [
                        'type' => 'custom',
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'russian_stop', 'russian_stemmer'],
                    ],
                    'russian_fuzzy' => [
                        'type' => 'custom',
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'asciifolding', 'russian_stop', 'russian_stemmer'],
                    ],
                    'english_custom' => [
                        'type' => 'custom',
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'english_stop', 'english_stemmer'],
                    ],
                    'english_fuzzy' => [
                        'type' => 'custom',
                        'tokenizer' => 'standard',
                        'filter' => ['lowercase', 'asciifolding', 'english_stop', 'english_stemmer'],
                    ],
                ],
                'filter' => [
                    'romanian_stop' => ['type' => 'stop', 'stopwords' => '_romanian_'],
                    'romanian_stemmer' => ['type' => 'stemmer', 'language' => 'romanian'],
                    'romanian_synonyms' => [
                        'type' => 'synonym',
                        'synonyms' => [
                            'BNM, Banca Nationala => Banca Națională a Moldovei',
                            'CNA => Centrul Național Anticorupție',
                            'UE, EU => Uniunea Europeană',
                            'CSM => Consiliul Superior al Magistraturii',
                            'PAS => Partidul Acțiune și Solidaritate',
                            'PSRM => Partidul Socialiștilor',
                            'RM, Moldova => Republica Moldova',
                            'MAIA, MApN => Ministerul Afacerilor Externe',
                            'SIS => Serviciul de Informații și Securitate',
                            'PG => Procuratura Generală',
                        ],
                    ],
                    'russian_stop' => ['type' => 'stop', 'stopwords' => '_russian_'],
                    'russian_stemmer' => ['type' => 'stemmer', 'language' => 'russian'],
                    'english_stop' => ['type' => 'stop', 'stopwords' => '_english_'],
                    'english_stemmer' => ['type' => 'stemmer', 'language' => 'english'],
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
                'article_id' => ['type' => 'integer'],

                // Trilingual title fields with fuzzy sub-fields for diacritics-insensitive search
                'title_ro' => [
                    'type' => 'text',
                    'analyzer' => 'romanian_custom',
                    'fields' => ['fuzzy' => ['type' => 'text', 'analyzer' => 'romanian_fuzzy']],
                ],
                'title_en' => [
                    'type' => 'text',
                    'analyzer' => 'english_custom',
                    'fields' => ['fuzzy' => ['type' => 'text', 'analyzer' => 'english_fuzzy']],
                ],
                'title_ru' => [
                    'type' => 'text',
                    'analyzer' => 'russian_custom',
                    'fields' => ['fuzzy' => ['type' => 'text', 'analyzer' => 'russian_fuzzy']],
                ],

                // Trilingual body fields with fuzzy sub-fields
                'body_ro' => [
                    'type' => 'text',
                    'analyzer' => 'romanian_custom',
                    'fields' => ['fuzzy' => ['type' => 'text', 'analyzer' => 'romanian_fuzzy']],
                ],
                'body_en' => [
                    'type' => 'text',
                    'analyzer' => 'english_custom',
                    'fields' => ['fuzzy' => ['type' => 'text', 'analyzer' => 'english_fuzzy']],
                ],
                'body_ru' => [
                    'type' => 'text',
                    'analyzer' => 'russian_custom',
                    'fields' => ['fuzzy' => ['type' => 'text', 'analyzer' => 'russian_fuzzy']],
                ],

                // Trilingual description fields with fuzzy sub-fields
                'description_ro' => [
                    'type' => 'text',
                    'analyzer' => 'romanian_custom',
                    'fields' => ['fuzzy' => ['type' => 'text', 'analyzer' => 'romanian_fuzzy']],
                ],
                'description_en' => [
                    'type' => 'text',
                    'analyzer' => 'english_custom',
                    'fields' => ['fuzzy' => ['type' => 'text', 'analyzer' => 'english_fuzzy']],
                ],
                'description_ru' => [
                    'type' => 'text',
                    'analyzer' => 'russian_custom',
                    'fields' => ['fuzzy' => ['type' => 'text', 'analyzer' => 'russian_fuzzy']],
                ],

                // Keyword fields
                'categories' => ['type' => 'keyword'],
                'tags' => ['type' => 'keyword'],
                'status' => ['type' => 'keyword'],
                'type' => ['type' => 'keyword'],
                'source_name' => ['type' => 'keyword'],
                'author' => ['type' => 'keyword'],

                // Date fields
                'date_published' => ['type' => 'date'],
                'date_created' => ['type' => 'date'],

                // Metadata
                'content_hash' => ['type' => 'keyword'],
                'entities' => ['type' => 'keyword'],
                'topics' => ['type' => 'keyword'],
            ],
        ];
    }
}
