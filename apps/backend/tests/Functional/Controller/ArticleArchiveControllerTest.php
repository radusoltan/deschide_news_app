<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Article;
use App\Entity\Category;
use App\Entity\Author;
use App\Entity\User;
use App\Enum\ArticleStatus;
use App\Enum\ArchiveReason;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Functional tests for Article Archive Controller (Admin API).
 *
 * Tests HTTP requests/responses for admin archive management endpoints
 * with authentication and authorization.
 *
 * Endpoints tested:
 * - POST /api/admin/articles/{id}/archive
 * - POST /api/admin/articles/{id}/unarchive
 * - POST /api/admin/articles/archive-bulk
 * - GET /api/admin/archive/stats
 */
class ArticleArchiveControllerTest extends WebTestCase
{
    private $client;
    private $entityManager;
    private ?User $adminUser = null;
    private ?User $regularUser = null;

    protected function setUp(): void
    {
        static::ensureKernelShutdown();
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get('doctrine')->getManager();

        // Create test users
        $this->createTestUsers();
    }

    protected function tearDown(): void
    {
        // Cleanup test users
        $this->cleanupTestUsers();

        if ($this->entityManager) {
            $this->entityManager->close();
            $this->entityManager = null;
        }

        $this->client = null;

        parent::tearDown();
    }

    // ======================
    // POST /api/admin/articles/{id}/archive Tests
    // ======================

    public function testArchiveArticleRequiresAuthentication(): void
    {
        $this->client->request('POST', '/api/admin/articles/1/archive', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['reason' => 'old_content']));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testArchiveArticleRequiresAdminRole(): void
    {
        // Login as regular user (non-admin)
        $token = $this->getAuthToken($this->regularUser);

        $this->client->request('POST', '/api/admin/articles/1/archive', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['reason' => 'old_content']));

        $this->assertResponseStatusCodeSame(Response::HTTP_FORBIDDEN);
    }

    public function testArchiveArticleSuccess(): void
    {
        // Create test article
        $article = $this->createTestArticle();
        $articleId = $article->getId();

        // Login as admin
        $token = $this->getAuthToken($this->adminUser);

        $this->client->request('POST', "/api/admin/articles/{$articleId}/archive", [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['reason' => 'old_content']));

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('Article archived successfully', $data['message']);
        $this->assertArrayHasKey('article', $data);
        $this->assertEquals($articleId, $data['article']['id']);
        $this->assertEquals('archived', $data['article']['status']);
        $this->assertNotNull($data['article']['archived_at']);
        $this->assertEquals('old_content', $data['article']['archive_reason']);

        // Cleanup
        $this->cleanupArticle($article);
    }

    public function testArchiveArticleNotFound(): void
    {
        $token = $this->getAuthToken($this->adminUser);

        $this->client->request('POST', '/api/admin/articles/999999/archive', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['reason' => 'old_content']));

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Article not found', $data['error']);
    }

    public function testArchiveArticleWithInvalidReason(): void
    {
        $article = $this->createTestArticle();
        $articleId = $article->getId();
        $token = $this->getAuthToken($this->adminUser);

        $this->client->request('POST', "/api/admin/articles/{$articleId}/archive", [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['reason' => 'invalid_reason']));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('Invalid archive reason', $data['error']);

        // Cleanup
        $this->cleanupArticle($article);
    }

    public function testArchiveArticleWithMissingReason(): void
    {
        $article = $this->createTestArticle();
        $articleId = $article->getId();
        $token = $this->getAuthToken($this->adminUser);

        $this->client->request('POST', "/api/admin/articles/{$articleId}/archive", [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Missing required field: reason', $data['error']);

        // Cleanup
        $this->cleanupArticle($article);
    }

    public function testArchiveArticleAlreadyArchived(): void
    {
        $article = $this->createArchivedArticle();
        $articleId = $article->getId();
        $token = $this->getAuthToken($this->adminUser);

        $this->client->request('POST', "/api/admin/articles/{$articleId}/archive", [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['reason' => 'old_content']));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Article is already archived', $data['error']);

        // Cleanup
        $this->cleanupArticle($article);
    }

    public function testArchiveArticleWithDifferentReasons(): void
    {
        $token = $this->getAuthToken($this->adminUser);
        $reasons = [
            'old_content',
            'outdated_info',
            'legal_request',
            'duplicate',
            'low_quality',
            'policy_violation',
            'manual',
        ];

        foreach ($reasons as $reason) {
            $article = $this->createTestArticle();
            $articleId = $article->getId();

            $this->client->request('POST', "/api/admin/articles/{$articleId}/archive", [], [], [
                'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
                'CONTENT_TYPE' => 'application/json',
            ], json_encode(['reason' => $reason]));

            $this->assertResponseIsSuccessful();

            $data = json_decode($this->client->getResponse()->getContent(), true);
            $this->assertTrue($data['success']);
            $this->assertEquals($reason, $data['article']['archive_reason']);

            // Cleanup
            $this->cleanupArticle($article);
        }
    }

    // ======================
    // POST /api/admin/articles/{id}/unarchive Tests
    // ======================

    public function testUnarchiveArticleRequiresAuthentication(): void
    {
        $this->client->request('POST', '/api/admin/articles/1/unarchive', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testUnarchiveArticleSuccess(): void
    {
        $article = $this->createArchivedArticle();
        $articleId = $article->getId();
        $token = $this->getAuthToken($this->adminUser);

        $this->client->request('POST', "/api/admin/articles/{$articleId}/unarchive", [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('Article unarchived successfully', $data['message']);
        $this->assertArrayHasKey('article', $data);
        $this->assertEquals($articleId, $data['article']['id']);
        $this->assertEquals('published', $data['article']['status']);
        $this->assertNull($data['article']['archived_at']);
        $this->assertNull($data['article']['archive_reason']);

        // Cleanup
        $this->cleanupArticle($article);
    }

    public function testUnarchiveArticleNotFound(): void
    {
        $token = $this->getAuthToken($this->adminUser);

        $this->client->request('POST', '/api/admin/articles/999999/unarchive', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_NOT_FOUND);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Article not found', $data['error']);
    }

    public function testUnarchiveArticleNotArchived(): void
    {
        $article = $this->createTestArticle(); // Published article
        $articleId = $article->getId();
        $token = $this->getAuthToken($this->adminUser);

        $this->client->request('POST', "/api/admin/articles/{$articleId}/unarchive", [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Article is not archived', $data['error']);

        // Cleanup
        $this->cleanupArticle($article);
    }

    // ======================
    // POST /api/admin/articles/archive-bulk Tests
    // ======================

    public function testBulkArchiveRequiresAuthentication(): void
    {
        $this->client->request('POST', '/api/admin/articles/archive-bulk', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['years_old' => 4]));

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testBulkArchiveSuccess(): void
    {
        // Create old articles (5 years old)
        $oldArticles = $this->createOldArticles(5, 3);
        $token = $this->getAuthToken($this->adminUser);

        $this->client->request('POST', '/api/admin/articles/archive-bulk', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'years_old' => 4,
            'batch_size' => 100,
        ]));

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertEquals('Bulk archive completed', $data['message']);
        $this->assertArrayHasKey('results', $data);
        $this->assertArrayHasKey('total_archived', $data['results']);
        $this->assertGreaterThanOrEqual(3, $data['results']['total_archived']);
        $this->assertEquals(100, $data['results']['batch_size']);
        $this->assertEquals(4, $data['results']['years_old']);
        $this->assertArrayHasKey('cutoff_date', $data['results']);

        // Cleanup
        foreach ($oldArticles as $article) {
            $this->cleanupArticle($article);
        }
    }

    public function testBulkArchiveWithInvalidYears(): void
    {
        $token = $this->getAuthToken($this->adminUser);

        // Test with 0 years
        $this->client->request('POST', '/api/admin/articles/archive-bulk', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['years_old' => 0]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('years_old must be positive', $data['error']);

        // Test with negative years
        $this->client->request('POST', '/api/admin/articles/archive-bulk', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode(['years_old' => -5]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testBulkArchiveWithInvalidBatchSize(): void
    {
        $token = $this->getAuthToken($this->adminUser);

        // Test with batch_size = 0
        $this->client->request('POST', '/api/admin/articles/archive-bulk', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'years_old' => 4,
            'batch_size' => 0,
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('batch_size must be between 1 and 1000', $data['error']);

        // Test with batch_size > 1000
        $this->client->request('POST', '/api/admin/articles/archive-bulk', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'years_old' => 4,
            'batch_size' => 1500,
        ]));

        $this->assertResponseStatusCodeSame(Response::HTTP_BAD_REQUEST);
    }

    public function testBulkArchiveWithDefaultParameters(): void
    {
        $token = $this->getAuthToken($this->adminUser);

        // Test with empty request body (should use defaults: 4 years, 100 batch size)
        $this->client->request('POST', '/api/admin/articles/archive-bulk', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([]));

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals(4, $data['results']['years_old']);
        $this->assertEquals(100, $data['results']['batch_size']);
    }

    // ======================
    // GET /api/admin/archive/stats Tests
    // ======================

    public function testGetAdminArchiveStatsRequiresAuth(): void
    {
        $this->client->request('GET', '/api/admin/archive/stats', [], [], [
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseStatusCodeSame(Response::HTTP_UNAUTHORIZED);
    }

    public function testGetAdminArchiveStatsReturnsDetailedMetrics(): void
    {
        // Create some archived articles with different reasons
        $articles = [];
        $articles[] = $this->createArchivedArticleWithReason(ArchiveReason::OLD_CONTENT);
        $articles[] = $this->createArchivedArticleWithReason(ArchiveReason::OUTDATED_INFO);
        $articles[] = $this->createArchivedArticleWithReason(ArchiveReason::DUPLICATE);

        $token = $this->getAuthToken($this->adminUser);

        $this->client->request('GET', '/api/admin/archive/stats', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);

        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('statistics', $data);
        $this->assertArrayHasKey('timestamp', $data);

        $stats = $data['statistics'];

        // Verify all expected keys exist
        $this->assertArrayHasKey('total_articles', $stats);
        $this->assertArrayHasKey('archived_articles', $stats);
        $this->assertArrayHasKey('archive_percentage', $stats);
        $this->assertArrayHasKey('by_reason', $stats);
        $this->assertArrayHasKey('archived_this_month', $stats);
        $this->assertArrayHasKey('archived_this_year', $stats);

        // Verify types
        $this->assertIsInt($stats['total_articles']);
        $this->assertIsInt($stats['archived_articles']);
        $this->assertIsFloat($stats['archive_percentage']);
        $this->assertIsArray($stats['by_reason']);

        // Verify by_reason contains at least the reasons we created
        // Note: API may only return reasons that exist in DB, not all enum values
        $this->assertArrayHasKey('old_content', $stats['by_reason']);
        $this->assertArrayHasKey('outdated_info', $stats['by_reason']);
        $this->assertArrayHasKey('duplicate', $stats['by_reason']);

        // Verify all returned values are integers
        foreach ($stats['by_reason'] as $reason => $count) {
            $this->assertIsString($reason);
            $this->assertIsInt($count);
            $this->assertGreaterThanOrEqual(0, $count);
        }

        // Cleanup
        foreach ($articles as $article) {
            $this->cleanupArticle($article);
        }
    }

    public function testGetAdminArchiveStatsIncludesOldestAndNewest(): void
    {
        $token = $this->getAuthToken($this->adminUser);

        $this->client->request('GET', '/api/admin/archive/stats', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
            'HTTP_ACCEPT' => 'application/json',
        ]);

        $this->assertResponseIsSuccessful();

        $data = json_decode($this->client->getResponse()->getContent(), true);
        $stats = $data['statistics'];

        // Check for oldest_archived if there are archived articles
        if ($stats['archived_articles'] > 0) {
            $this->assertArrayHasKey('oldest_archived', $stats);
            if ($stats['oldest_archived'] !== null) {
                $this->assertArrayHasKey('id', $stats['oldest_archived']);
                $this->assertArrayHasKey('title', $stats['oldest_archived']);
                $this->assertArrayHasKey('archived_at', $stats['oldest_archived']);
                $this->assertArrayHasKey('reason', $stats['oldest_archived']);
            }

            $this->assertArrayHasKey('most_recent_archived', $stats);
            if ($stats['most_recent_archived'] !== null) {
                $this->assertArrayHasKey('id', $stats['most_recent_archived']);
                $this->assertArrayHasKey('title', $stats['most_recent_archived']);
                $this->assertArrayHasKey('archived_at', $stats['most_recent_archived']);
                $this->assertArrayHasKey('reason', $stats['most_recent_archived']);
            }
        }
    }

    // ======================
    // Helper Methods
    // ======================

    private function createTestUsers(): void
    {
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        // Create admin user
        $this->adminUser = new User();
        $this->adminUser->setUsername('test_admin_' . uniqid());
        $this->adminUser->setEmail('test_admin_' . uniqid() . '@example.com');
        $this->adminUser->setRoles(['ROLE_ADMIN']);
        $this->adminUser->setPassword($passwordHasher->hashPassword($this->adminUser, 'password123'));
        $this->entityManager->persist($this->adminUser);

        // Create regular user
        $this->regularUser = new User();
        $this->regularUser->setUsername('test_user_' . uniqid());
        $this->regularUser->setEmail('test_user_' . uniqid() . '@example.com');
        $this->regularUser->setRoles(['ROLE_USER']);
        $this->regularUser->setPassword($passwordHasher->hashPassword($this->regularUser, 'password123'));
        $this->entityManager->persist($this->regularUser);

        $this->entityManager->flush();
    }

    private function cleanupTestUsers(): void
    {
        if ($this->adminUser && $this->entityManager->contains($this->adminUser)) {
            $this->entityManager->remove($this->adminUser);
        }

        if ($this->regularUser && $this->entityManager->contains($this->regularUser)) {
            $this->entityManager->remove($this->regularUser);
        }

        $this->entityManager->flush();
    }

    private function getAuthToken(?User $user = null): string
    {
        if ($user === null) {
            $user = $this->adminUser;
        }

        // Login and get JWT token
        $this->client->request('POST', '/api/login_check', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'username' => $user->getUsername(),
            'password' => 'password123',
        ]));

        $response = json_decode($this->client->getResponse()->getContent(), true);

        // Add null check and better error handling
        if (!isset($response['token'])) {
            throw new \RuntimeException(
                'Failed to get auth token. Response: ' . $this->client->getResponse()->getContent()
            );
        }

        return $response['token'];
    }

    private function createTestArticle(): Article
    {
        $category = new Category();
        $category->setTitle('Test Category');
        $category->setSlug('test-category-' . uniqid());
        $this->entityManager->persist($category);

        $author = new Author();
        $author->setFirstName('Test');
        $author->setLastName('Author');
        $author->setEmail('test-' . uniqid() . '@example.com');
        $author->setSlug('test-author-' . uniqid());
        $this->entityManager->persist($author);

        $article = new Article();
        $article->setTitle('Test Article');
        $article->setSlug('test-article-' . uniqid());
        $article->setLead('Test lead');
        $article->setContent('Test content');
        $article->setStatus(ArticleStatus::PUBLISHED);
        $article->setPublishedAt(new DateTimeImmutable());
        $article->setCategory($category);
        $article->addAuthor($author);

        $this->entityManager->persist($article);
        $this->entityManager->flush();

        return $article;
    }

    private function createArchivedArticle(): Article
    {
        $article = $this->createTestArticle();
        $article->setStatus(ArticleStatus::ARCHIVED);
        $article->setArchiveReason(ArchiveReason::OLD_CONTENT);
        $article->setArchivedAt(new DateTimeImmutable());

        $this->entityManager->flush();

        return $article;
    }

    private function createArchivedArticleWithReason(ArchiveReason $reason): Article
    {
        $article = $this->createTestArticle();
        $article->setStatus(ArticleStatus::ARCHIVED);
        $article->setArchiveReason($reason);
        $article->setArchivedAt(new DateTimeImmutable());

        $this->entityManager->flush();

        return $article;
    }

    private function createOldArticles(int $yearsOld, int $count): array
    {
        $articles = [];
        $publishDate = (new DateTimeImmutable())->modify("-{$yearsOld} years");

        $category = new Category();
        $category->setTitle('Old Category');
        $category->setSlug('old-category-' . uniqid());
        $this->entityManager->persist($category);

        $author = new Author();
        $author->setFirstName('Old');
        $author->setLastName('Author');
        $author->setEmail('old-' . uniqid() . '@example.com');
        $author->setSlug('old-author-' . uniqid());
        $this->entityManager->persist($author);

        for ($i = 0; $i < $count; $i++) {
            $article = new Article();
            $article->setTitle("Old Article {$i}");
            $article->setSlug("old-article-{$i}-" . uniqid());
            $article->setLead('Old lead');
            $article->setContent('Old content');
            $article->setStatus(ArticleStatus::PUBLISHED);
            $article->setPublishedAt($publishDate);
            $article->setCategory($category);
            $article->addAuthor($author);

            $this->entityManager->persist($article);
            $articles[] = $article;
        }

        $this->entityManager->flush();

        return $articles;
    }

    private function cleanupArticle(Article $article): void
    {
        if (!$this->entityManager->contains($article)) {
            $article = $this->entityManager->find(Article::class, $article->getId());
        }

        if ($article) {
            $category = $article->getCategory();
            $authors = $article->getAuthors()->toArray();

            // First remove the article
            $this->entityManager->remove($article);
            $this->entityManager->flush();

            // Then remove related entities (category and authors) - only if no other articles use them
            if ($category) {
                try {
                    // Check if category is still being used by other articles
                    $articlesCount = $this->entityManager->createQueryBuilder()
                        ->select('COUNT(a.id)')
                        ->from(Article::class, 'a')
                        ->where('a.category = :category')
                        ->setParameter('category', $category)
                        ->getQuery()
                        ->getSingleScalarResult();

                    if ($articlesCount == 0) {
                        $this->entityManager->remove($category);
                        $this->entityManager->flush();
                    }
                } catch (\Exception $e) {
                    // Category already deleted or other error, ignore
                }
            }

            foreach ($authors as $author) {
                if ($author) {
                    try {
                        // Check if author is still being used by other articles
                        $articlesCount = $this->entityManager->createQueryBuilder()
                            ->select('COUNT(a.id)')
                            ->from(Article::class, 'a')
                            ->join('a.authors', 'au')
                            ->where('au.id = :author')
                            ->setParameter('author', $author->getId())
                            ->getQuery()
                            ->getSingleScalarResult();

                        if ($articlesCount == 0) {
                            $this->entityManager->remove($author);
                            $this->entityManager->flush();
                        }
                    } catch (\Exception $e) {
                        // Author already deleted or other error, ignore
                    }
                }
            }
        }
    }
}
