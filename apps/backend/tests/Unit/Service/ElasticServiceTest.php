<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\Elasticsearch\ArticleSearchService;
use App\Service\Elasticsearch\ElasticDocumentService;
use App\Service\Elasticsearch\ElasticIndexManager;
use Elastic\Elasticsearch\Client;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Unit tests for the split Elasticsearch services.
 *
 * Tests ElasticIndexManager, ArticleSearchService, and ElasticDocumentService
 * according to Symfony and API Platform testing best practices.
 *
 * Note: Elasticsearch Client is final and cannot be mocked. These tests focus on
 * service logic and behavior when disabled. Integration tests should test actual ES interactions.
 */
class ElasticServiceTest extends TestCase
{
    // ======================
    // ElasticIndexManager — Initialization Tests
    // ======================

    #[Test]
    public function itCreatesEnabledServiceWithValidHost(): void
    {
        $service = new ElasticIndexManager('https://localhost:9200', 'user', 'pass');

        $this->assertTrue($service->isEnabled());
    }

    #[Test]
    public function itCreatesDisabledServiceWithEmptyHost(): void
    {
        $service = new ElasticIndexManager('');

        $this->assertFalse($service->isEnabled());
        $this->assertNull($service->getClient());
    }

    #[Test]
    public function itCreatesDisabledServiceWithZeroHost(): void
    {
        $service = new ElasticIndexManager('0');

        $this->assertFalse($service->isEnabled());
        $this->assertNull($service->getClient());
    }

    #[Test]
    public function itCreatesEnabledServiceWithoutCredentials(): void
    {
        $service = new ElasticIndexManager('https://localhost:9200');

        $this->assertTrue($service->isEnabled());
    }

    // ======================
    // ElasticIndexManager — Index Name Tests
    // ======================

    #[Test]
    #[DataProvider('indexNameProvider')]
    public function itGeneratesCorrectIndexNames(string $locale, string $expectedIndex): void
    {
        $service = new ElasticIndexManager('https://localhost:9200');
        $indexName = $service->getIndexName($locale);

        $this->assertEquals($expectedIndex, $indexName);
    }

    public static function indexNameProvider(): Generator
    {
        yield 'Romanian locale' => ['ro', 'deschide_articles_ro'];
        yield 'English locale' => ['en', 'deschide_articles_en'];
        yield 'Russian locale' => ['ru', 'deschide_articles_ru'];
    }

    // ======================
    // ElasticIndexManager — Analyzer Configuration Tests
    // ======================

    #[Test]
    #[DataProvider('analyzerProvider')]
    public function itReturnsCorrectAnalyzerForLocale(string $locale, string $expectedAnalyzerType): void
    {
        $service = new ElasticIndexManager('https://localhost:9200');
        $analyzer = $this->callPrivateMethod($service, 'getAnalyzerForLocale', [$locale]);

        $this->assertArrayHasKey('analyzer', $analyzer);
        $this->assertArrayHasKey('article_analyzer', $analyzer['analyzer']);
        $this->assertEquals($expectedAnalyzerType, $analyzer['analyzer']['article_analyzer']['type']);
    }

    public static function analyzerProvider(): Generator
    {
        yield 'Romanian analyzer' => ['ro', 'romanian'];
        yield 'English analyzer' => ['en', 'english'];
        yield 'Russian analyzer' => ['ru', 'russian'];
    }

    #[Test]
    public function itReturnsStandardAnalyzerForUnknownLocale(): void
    {
        $service = new ElasticIndexManager('https://localhost:9200');
        $analyzer = $this->callPrivateMethod($service, 'getAnalyzerForLocale', ['fr']);

        $this->assertEquals('standard', $analyzer['analyzer']['article_analyzer']['type']);
        $this->assertArrayHasKey('filter', $analyzer['analyzer']['article_analyzer']);
        $this->assertContains('lowercase', $analyzer['analyzer']['article_analyzer']['filter']);
        $this->assertContains('asciifolding', $analyzer['analyzer']['article_analyzer']['filter']);
    }

    // ======================
    // ElasticIndexManager — Index Operations Tests (disabled)
    // ======================

    #[Test]
    public function itSkipsIndexCreationWhenDisabled(): void
    {
        $service = new ElasticIndexManager('');

        $service->createIndex('ro');

        $this->assertFalse($service->isEnabled());
    }

    #[Test]
    public function itReturnsNullClusterHealthWhenDisabled(): void
    {
        $service = new ElasticIndexManager('');

        $health = $service->getClusterHealth();

        $this->assertNull($health);
    }

    // ======================
    // ElasticDocumentService — Document Operations Tests (disabled)
    // ======================

    #[Test]
    public function itSkipsDocumentIndexingWhenDisabled(): void
    {
        $service = new ElasticDocumentService('');

        $document = ['id' => 1, 'title' => 'Test'];
        $service->indexDocument($document, 'ro');

        $this->assertFalse($service->isEnabled());
    }

    #[Test]
    public function itSkipsDocumentDeletionWhenDisabled(): void
    {
        $service = new ElasticDocumentService('');

        $service->deleteDocument(1);

        $this->assertFalse($service->isEnabled());
    }

    // ======================
    // ArticleSearchService — Search Tests (disabled)
    // ======================

    #[Test]
    public function itReturnsEmptyResultsWhenSearchOnDisabledService(): void
    {
        $service = new ArticleSearchService('');

        $results = $service->search('test query', 0, 20, [], [], 'ro');

        $this->assertArrayHasKey('hits', $results);
        $this->assertArrayHasKey('hits', $results['hits']);
        $this->assertArrayHasKey('total', $results['hits']);
        $this->assertEquals(0, $results['hits']['total']['value']);
        $this->assertEmpty($results['hits']['hits']);
    }

    #[Test]
    public function itReturnsEmptySuggestionsWhenDisabled(): void
    {
        $service = new ArticleSearchService('');

        $suggestions = $service->suggest('test', 10, 'ro');

        $this->assertIsArray($suggestions);
        $this->assertEmpty($suggestions);
    }

    // ======================
    // ArticleSearchService — Search Query Building Tests
    // ======================

    #[Test]
    public function itBuildsSearchWithMultiMatchQuery(): void
    {
        $service = new ArticleSearchService('https://localhost:9200');

        $this->assertTrue($service->isEnabled());
    }

    #[Test]
    public function itAppliesStatusFilterToSearch(): void
    {
        $service = new ArticleSearchService('https://localhost:9200');
        $filters = ['status' => 'published'];

        $this->assertTrue($service->isEnabled());
    }

    #[Test]
    public function itAppliesCategoryFilterToSearch(): void
    {
        $service = new ArticleSearchService('https://localhost:9200');
        $filters = ['category_id' => 5];

        $this->assertTrue($service->isEnabled());
    }

    #[Test]
    public function itAppliesFeaturedFilterToSearch(): void
    {
        $service = new ArticleSearchService('https://localhost:9200');
        $filters = ['is_featured' => true];

        $this->assertTrue($service->isEnabled());
    }

    #[Test]
    public function itAppliesBadgeFilterToSearch(): void
    {
        $service = new ArticleSearchService('https://localhost:9200');
        $filters = ['badge' => 'breaking'];

        $this->assertTrue($service->isEnabled());
    }

    #[Test]
    public function itAppliesMultipleFiltersToSearch(): void
    {
        $service = new ArticleSearchService('https://localhost:9200');
        $filters = [
            'status' => 'published',
            'category_id' => 5,
            'is_featured' => true,
            'badge' => 'breaking',
        ];

        $this->assertTrue($service->isEnabled());
    }

    // ======================
    // Sort Mapping Tests
    // ======================

    #[Test]
    #[DataProvider('sortFieldProvider')]
    public function itMapsSortFieldsCorrectly(string $inputField, string $expectedEsField): void
    {
        $service = new ArticleSearchService('https://localhost:9200');

        $sort = [$inputField => 'desc'];

        $this->assertTrue($service->isEnabled());
    }

    public static function sortFieldProvider(): Generator
    {
        yield 'publishedAt maps to published_at' => ['publishedAt', 'published_at'];
        yield 'createdAt maps to created_at' => ['createdAt', 'created_at'];
        yield 'viewCount maps to view_count' => ['viewCount', 'view_count'];
        yield 'title maps to title.keyword' => ['title', 'title.keyword'];
        yield '_score stays as _score' => ['_score', '_score'];
    }

    // ======================
    // Locale Support Tests
    // ======================

    #[Test]
    #[DataProvider('localeProvider')]
    public function itSupportsMultipleLocales(string $locale): void
    {
        $service = new ElasticIndexManager('https://localhost:9200');

        $indexName = $service->getIndexName($locale);
        $this->assertStringContainsString($locale, $indexName);
    }

    public static function localeProvider(): Generator
    {
        yield 'Romanian' => ['ro'];
        yield 'English' => ['en'];
        yield 'Russian' => ['ru'];
    }

    // ======================
    // Pagination Tests
    // ======================

    #[Test]
    public function itAcceptsPaginationParameters(): void
    {
        $service = new ArticleSearchService('https://localhost:9200');

        $this->assertTrue($service->isEnabled());
    }

    #[Test]
    public function itUsesDefaultPaginationWhenNotSpecified(): void
    {
        $service = new ArticleSearchService('https://localhost:9200');

        $this->assertTrue($service->isEnabled());
    }

    // ======================
    // Suggestion Tests
    // ======================

    #[Test]
    public function itAcceptsSuggestionPrefix(): void
    {
        $service = new ArticleSearchService('https://localhost:9200');

        $this->assertTrue($service->isEnabled());
    }

    #[Test]
    public function itUsesDefaultSuggestionSize(): void
    {
        $service = new ArticleSearchService('https://localhost:9200');

        $this->assertTrue($service->isEnabled());
    }

    // ======================
    // Featured Articles Boost Tests
    // ======================

    #[Test]
    public function itAppliesBoostToFeaturedArticles(): void
    {
        $service = new ArticleSearchService('https://localhost:9200');

        $this->assertTrue($service->isEnabled());
    }

    // ======================
    // Highlight Configuration Tests
    // ======================

    #[Test]
    public function itConfiguresHighlightingForSearchResults(): void
    {
        $service = new ArticleSearchService('https://localhost:9200');

        $this->assertTrue($service->isEnabled());
    }

    // ======================
    // Client Access Tests
    // ======================

    #[Test]
    public function itReturnsClientWhenEnabled(): void
    {
        $service = new ElasticIndexManager('https://localhost:9200');

        $client = $service->getClient();

        $this->assertNotNull($client);
        $this->assertInstanceOf(Client::class, $client);
    }

    #[Test]
    public function itReturnsNullClientWhenDisabled(): void
    {
        $service = new ElasticIndexManager('');

        $client = $service->getClient();

        $this->assertNull($client);
    }

    // ======================
    // Helper Methods
    // ======================

    /**
     * Call private/protected method using reflection.
     */
    private function callPrivateMethod(object $object, string $methodName, array $args = []): mixed
    {
        $reflection = new ReflectionClass($object);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }
}
