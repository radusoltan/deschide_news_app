<?php

declare(strict_types=1);

namespace App\Service\Elasticsearch;

use Elastic\Elasticsearch\Client;
use Elastic\Elasticsearch\ClientBuilder;
use Exception;
use RuntimeException;

/**
 * Handles Elasticsearch document operations: index, update, delete, bulk index.
 *
 * Split from App\Service\ElasticService (T38.4 — SRP).
 */
class ElasticDocumentService
{
    private ?Client $client;

    private string $indexPrefix = 'deschide_articles';

    private array $supportedLocales = ['ro', 'en', 'ru'];

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

        foreach ($this->supportedLocales as $locale) {
            $indexName = $this->getIndexName($locale);

            $params = [
                'index' => $indexName,
                'id' => $id,
            ];

            try {
                $this->client->delete($params);
            } catch (Exception $e) {
                if (!str_contains($e->getMessage(), 'not_found')) {
                    error_log("Failed to delete document {$id} from {$indexName}: " . $e->getMessage());
                }
            }
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
            $params['body'][] = [
                'index' => [
                    '_index' => $indexName,
                    '_id' => $document['id'],
                ],
            ];

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
     * Get index name for a specific locale.
     */
    private function getIndexName(string $locale): string
    {
        return $this->indexPrefix . '_' . $locale;
    }
}
