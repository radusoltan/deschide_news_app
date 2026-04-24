<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Elasticsearch;

use App\Service\Aggregator\ElasticsearchSimilarityService;
use App\Service\Elasticsearch\ArticleSearchService;
use App\Service\Elasticsearch\ElasticDocumentService;
use App\Service\Elasticsearch\ElasticIndexManager;
use App\Service\ImageElasticService;
use App\Service\Search\ElasticsearchIndexManager as SearchElasticsearchIndexManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Verifies ADR-023 D9: ES index names are composed from the injected
 * $elasticsearchIndexPrefix constructor argument (wired via env var),
 * not from hardcoded 'deschide_*' string literals.
 *
 * Each test instantiates a service with a non-default prefix and asserts
 * the private index name property reflects that prefix. This guards against
 * any future regression that reintroduces a hardcoded string.
 */
final class IndexPrefixWiringTest extends TestCase
{
    private const CUSTOM_PREFIX = 'test_staging';

    #[Test]
    public function elasticDocumentServiceDerivesIndexPrefixFromConstructorArg(): void
    {
        $service = new ElasticDocumentService('', '', '', true, self::CUSTOM_PREFIX);

        self::assertSame(
            'test_staging_articles',
            $this->readPrivate($service, 'indexPrefix'),
        );
    }

    #[Test]
    public function elasticIndexManagerDerivesIndexPrefixFromConstructorArg(): void
    {
        $service = new ElasticIndexManager('', '', '', true, self::CUSTOM_PREFIX);

        self::assertSame(
            'test_staging_articles',
            $this->readPrivate($service, 'indexPrefix'),
        );
    }

    #[Test]
    public function articleSearchServiceDerivesIndexPrefixFromConstructorArg(): void
    {
        $service = new ArticleSearchService('', '', '', true, self::CUSTOM_PREFIX);

        self::assertSame(
            'test_staging_articles',
            $this->readPrivate($service, 'indexPrefix'),
        );
    }

    #[Test]
    public function elasticsearchSimilarityServiceDerivesIndexNameFromConstructorArg(): void
    {
        $service = new ElasticsearchSimilarityService(
            '',
            '',
            '',
            true,
            new \Psr\Log\NullLogger(),
            self::CUSTOM_PREFIX,
        );

        self::assertSame(
            'test_staging_articles_trilingual',
            $this->readPrivate($service, 'indexName'),
        );
    }

    #[Test]
    public function searchElasticsearchIndexManagerExposesPrefixedIndexName(): void
    {
        $service = new SearchElasticsearchIndexManager(
            '',
            '',
            '',
            true,
            new \Psr\Log\NullLogger(),
            self::CUSTOM_PREFIX,
        );

        self::assertSame('test_staging_articles_trilingual', $service->getIndexName());
    }

    #[Test]
    public function imageElasticServiceDerivesIndexNameFromConstructorArg(): void
    {
        $service = new ImageElasticService('', '', '', true, self::CUSTOM_PREFIX);

        self::assertSame(
            'test_staging_images',
            $this->readPrivate($service, 'indexName'),
        );
    }

    #[Test]
    public function defaultPrefixPreservesLegacyIndexNamesForBackwardsCompatibility(): void
    {
        // No prefix passed → default 'deschide' → legacy index names unchanged
        $doc = new ElasticDocumentService('');
        $mgr = new ElasticIndexManager('');
        $search = new ArticleSearchService('');
        $img = new ImageElasticService('');
        $sim = new ElasticsearchSimilarityService('');
        $trilingual = new SearchElasticsearchIndexManager('');

        self::assertSame('deschide_articles', $this->readPrivate($doc, 'indexPrefix'));
        self::assertSame('deschide_articles', $this->readPrivate($mgr, 'indexPrefix'));
        self::assertSame('deschide_articles', $this->readPrivate($search, 'indexPrefix'));
        self::assertSame('deschide_images', $this->readPrivate($img, 'indexName'));
        self::assertSame('deschide_articles_trilingual', $this->readPrivate($sim, 'indexName'));
        self::assertSame('deschide_articles_trilingual', $trilingual->getIndexName());
    }

    private function readPrivate(object $service, string $property): mixed
    {
        return (new ReflectionClass($service))->getProperty($property)->getValue($service);
    }
}
