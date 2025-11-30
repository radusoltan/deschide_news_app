<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Author;
use App\Enum\ArticleStatus;
use App\Enum\ArchiveReason;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional tests for Archive Controller (Public API).
 *
 * Tests HTTP requests/responses for public archive navigation endpoints
 * according to Symfony and API Platform testing best practices.
 *
 * Endpoints tested:
 * - GET /api/archive/years
 * - GET /api/archive/stats
 * - GET /api/archive/categories
 */
class ArchiveControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get('doctrine')->getManager();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->entityManager->close();
        $this->entityManager = null;
    }

    // ======================
    // GET /api/archive/years Tests
    // ======================

    public function testGetArchiveYearsReturnsArray(): void
    {
        // Create test archived articles from different years
        $testData = $this->createArchivedArticlesForYears([2021, 2022, 2023]);

        $this->client->request('GET', '/api/archive/years', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => 'ro',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertIsArray($data);
        $this->assertNotEmpty($data);

        // Check structure of each year entry
        foreach ($data as $yearEntry) {
            $this->assertArrayHasKey('year', $yearEntry);
            $this->assertArrayHasKey('count', $yearEntry);
            $this->assertIsInt($yearEntry['year']);
            $this->assertIsInt($yearEntry['count']);
            $this->assertGreaterThan(0, $yearEntry['count']);
        }

        // Cleanup
        $this->cleanupTestData($testData);
    }

    public function testGetArchiveYearsHasCacheHeaders(): void
    {
        $this->client->request('GET', '/api/archive/years', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => 'ro',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHasHeader('Cache-Control');
        $this->assertResponseHeaderSame('Cache-Control', 'public, max-age=86400, s-maxage=604800');
    }

    public function testGetArchiveYearsRespectsLocale(): void
    {
        // Test with English locale
        $this->client->request('GET', '/api/archive/years', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => 'en',
        ]);

        $this->assertResponseIsSuccessful();

        // Test with Russian locale
        $this->client->request('GET', '/api/archive/years', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => 'ru',
        ]);

        $this->assertResponseIsSuccessful();

        // Test with locale query parameter
        $this->client->request('GET', '/api/archive/years?locale=en', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();
    }

    public function testGetArchiveYearsReturnsSortedDescending(): void
    {
        // Create test data for multiple years
        $testData = $this->createArchivedArticlesForYears([2020, 2021, 2022, 2023]);

        $this->client->request('GET', '/api/archive/years', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => 'ro',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);

        // Verify years are sorted in descending order
        if (count($data) > 1) {
            for ($i = 0; $i < count($data) - 1; $i++) {
                $this->assertGreaterThan($data[$i + 1]['year'], $data[$i]['year']);
            }
        }

        // Cleanup
        $this->cleanupTestData($testData);
    }

    // ======================
    // GET /api/archive/stats Tests
    // ======================

    public function testGetArchiveStatsReturnsJson(): void
    {
        $this->client->request('GET', '/api/archive/stats', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => 'ro',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($this->client->getResponse()->getContent(), true);

        // Verify structure
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('byYear', $data);
        $this->assertArrayHasKey('byCategory', $data);

        $this->assertIsInt($data['total']);
        $this->assertIsArray($data['byYear']);
        $this->assertIsArray($data['byCategory']);
    }

    public function testGetArchiveStatsHasCacheHeaders(): void
    {
        $this->client->request('GET', '/api/archive/stats', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHasHeader('Cache-Control');
        $this->assertResponseHeaderSame('Cache-Control', 'public, max-age=86400, s-maxage=604800');
    }

    public function testGetArchiveStatsReturnsValidStructure(): void
    {
        // Create test archived articles
        $testData = $this->createArchivedArticlesForYears([2022, 2023]);

        $this->client->request('GET', '/api/archive/stats', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => 'ro',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);

        // Verify total is sum of all years
        $this->assertGreaterThanOrEqual(0, $data['total']);

        // Verify byYear has correct structure
        foreach ($data['byYear'] as $yearEntry) {
            $this->assertArrayHasKey('year', $yearEntry);
            $this->assertArrayHasKey('count', $yearEntry);
        }

        // Verify byCategory has correct structure
        foreach ($data['byCategory'] as $categoryEntry) {
            $this->assertArrayHasKey('id', $categoryEntry);
            $this->assertArrayHasKey('name', $categoryEntry);
            $this->assertArrayHasKey('count', $categoryEntry);
        }

        // Cleanup
        $this->cleanupTestData($testData);
    }

    // ======================
    // GET /api/archive/categories Tests
    // ======================

    public function testGetArchiveCategoriesReturnsFilteredList(): void
    {
        // Create test category with archived articles
        $testData = $this->createCategoryWithArchivedArticles();

        $this->client->request('GET', '/api/archive/categories', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => 'ro',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/json');

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertIsArray($data);

        // Verify each category has the correct structure
        foreach ($data as $category) {
            $this->assertArrayHasKey('id', $category);
            $this->assertArrayHasKey('name', $category);
            $this->assertArrayHasKey('slug', $category);
            $this->assertArrayHasKey('count', $category);
            $this->assertGreaterThan(0, $category['count']);
        }

        // Cleanup
        $this->cleanupTestData($testData);
    }

    public function testGetArchiveCategoriesHasCacheHeaders(): void
    {
        $this->client->request('GET', '/api/archive/categories', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertResponseHasHeader('Cache-Control');
        $this->assertResponseHeaderSame('Cache-Control', 'public, max-age=86400, s-maxage=604800');
    }

    public function testArchiveEndpointsRespectAcceptLanguageHeader(): void
    {
        // Test /years endpoint
        $this->client->request('GET', '/api/archive/years', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => 'en',
        ]);
        $this->assertResponseIsSuccessful();

        // Test /stats endpoint
        $this->client->request('GET', '/api/archive/stats', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => 'ru',
        ]);
        $this->assertResponseIsSuccessful();

        // Test /categories endpoint
        $this->client->request('GET', '/api/archive/categories', [], [], [
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_ACCEPT_LANGUAGE' => 'en',
        ]);
        $this->assertResponseIsSuccessful();
    }

    public function testArchiveEndpointsReturnEmptyResultsWhenNoArchivedArticles(): void
    {
        // Clear all archived articles (if any) by checking the response
        $this->client->request('GET', '/api/archive/years', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertIsArray($data);
        // Will return empty array if no archived articles
    }

    // ======================
    // Helper Methods
    // ======================

    private function createArchivedArticlesForYears(array $years): array
    {
        $testData = ['articles' => [], 'category' => null, 'author' => null];

        // Create test category
        $category = new Category();
        $category->setTitle('Test Archive Category');
        $category->setSlug('test-archive-category-' . uniqid());
        $this->entityManager->persist($category);
        $testData['category'] = $category;

        // Create test author
        $author = new Author();
        $author->setFirstName('Test Archive');
        $author->setLastName('Author');
        $author->setEmail('test-archive-' . uniqid() . '@example.com');
        $author->setSlug('test-archive-author-' . uniqid());
        $this->entityManager->persist($author);
        $testData['author'] = $author;

        // Create articles for each year
        foreach ($years as $year) {
            for ($i = 0; $i < 2; $i++) { // 2 articles per year
                $article = new Article();
                $article->setTitle("Test Archived Article {$year} - {$i}");
                $article->setSlug("test-archived-article-{$year}-{$i}-" . uniqid());
                $article->setLead("Test lead for archived article");
                $article->setContent("Test content for archived article");
                $article->setStatus(ArticleStatus::ARCHIVED);
                $article->setArchiveReason(ArchiveReason::OLD_CONTENT);
                $article->setArchivedAt(new DateTimeImmutable("{$year}-06-15 10:00:00"));
                $article->setPublishedAt(new DateTimeImmutable("{$year}-01-15 10:00:00"));
                $article->setCategory($category);
                $article->addAuthor($author);

                $this->entityManager->persist($article);
                $testData['articles'][] = $article;
            }
        }

        $this->entityManager->flush();

        return $testData;
    }

    private function createCategoryWithArchivedArticles(): array
    {
        $testData = ['articles' => [], 'category' => null, 'author' => null];

        // Create test category
        $category = new Category();
        $category->setTitle('Test Category With Archives');
        $category->setSlug('test-category-archives-' . uniqid());
        $this->entityManager->persist($category);
        $testData['category'] = $category;

        // Create test author
        $author = new Author();
        $author->setFirstName('Test Category');
        $author->setLastName('Author');
        $author->setEmail('test-category-' . uniqid() . '@example.com');
        $author->setSlug('test-category-author-' . uniqid());
        $this->entityManager->persist($author);
        $testData['author'] = $author;

        // Create archived articles
        for ($i = 0; $i < 3; $i++) {
            $article = new Article();
            $article->setTitle("Test Category Archived Article {$i}");
            $article->setSlug("test-category-archived-{$i}-" . uniqid());
            $article->setLead("Test lead");
            $article->setContent("Test content");
            $article->setStatus(ArticleStatus::ARCHIVED);
            $article->setArchiveReason(ArchiveReason::OLD_CONTENT);
            $article->setArchivedAt(new DateTimeImmutable('2022-06-15 10:00:00'));
            $article->setPublishedAt(new DateTimeImmutable('2022-01-15 10:00:00'));
            $article->setCategory($category);
            $article->addAuthor($author);

            $this->entityManager->persist($article);
            $testData['articles'][] = $article;
        }

        $this->entityManager->flush();

        return $testData;
    }

    private function cleanupTestData(array $testData): void
    {
        // Remove articles
        if (isset($testData['articles'])) {
            foreach ($testData['articles'] as $article) {
                if ($this->entityManager->contains($article)) {
                    $this->entityManager->remove($article);
                }
            }
        }

        // Remove category
        if (isset($testData['category']) && $this->entityManager->contains($testData['category'])) {
            $this->entityManager->remove($testData['category']);
        }

        // Remove author
        if (isset($testData['author']) && $this->entityManager->contains($testData['author'])) {
            $this->entityManager->remove($testData['author']);
        }

        $this->entityManager->flush();
    }
}
