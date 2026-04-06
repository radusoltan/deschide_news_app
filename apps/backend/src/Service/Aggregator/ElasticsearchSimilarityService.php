<?php

declare(strict_types=1);

namespace App\Service\Aggregator;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Psr\Log\LoggerInterface;

class ElasticsearchSimilarityService
{
    private ?Client $client;
    private readonly bool $enabled;

    public function __construct(
        string $elasticsearchHost,
        string $elasticsearchUser = '',
        string $elasticsearchPassword = '',
        bool $elasticsearchVerifySsl = true,
        private readonly LoggerInterface $logger = new \Psr\Log\NullLogger(),
        private readonly string $indexName = 'deschide_articles_trilingual',
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

    /**
     * Find similar articles using Elasticsearch more_like_this query.
     *
     * @return list<array{score: float, articleId: int, title: string}>
     */
    public function findSimilar(string $title, string $content, float $minScore = 0.6): array
    {
        if (!$this->enabled || $this->client === null) {
            $this->logger->debug('ElasticsearchSimilarityService: ES disabled, skipping.');

            return [];
        }

        try {
            $response = $this->client->search([
                'index' => $this->indexName,
                'body' => [
                    'query' => [
                        'more_like_this' => [
                            'fields' => ['title', 'content'],
                            'like' => [
                                [
                                    '_index' => $this->indexName,
                                    'doc' => [
                                        'title' => $title,
                                        'content' => mb_substr($content, 0, 5000),
                                    ],
                                ],
                            ],
                            'min_term_freq' => 1,
                            'min_doc_freq' => 1,
                            'minimum_should_match' => '30%',
                            'max_query_terms' => 25,
                        ],
                    ],
                    'min_score' => $minScore,
                    'size' => 5,
                    '_source' => ['title'],
                ],
            ]);

            $results = [];
            $hits = $response->asArray()['hits']['hits'] ?? [];

            foreach ($hits as $hit) {
                $results[] = [
                    'score' => (float) $hit['_score'],
                    'articleId' => (int) $hit['_id'],
                    'title' => $hit['_source']['title'] ?? '',
                ];
            }

            $this->logger->info('ElasticsearchSimilarityService: found {count} similar', [
                'count' => \count($results),
                'queryTitle' => mb_substr($title, 0, 80),
            ]);

            return $results;
        } catch (\Throwable $e) {
            $this->logger->error('ElasticsearchSimilarityService: search failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
