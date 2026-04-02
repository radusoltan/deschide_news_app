<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Article;
use App\Entity\Author;
use App\Entity\Category;
use App\Enum\ArticleStatus;
use App\Tests\Functional\ApiTestCase;
use DateTimeImmutable;

/**
 * Functional tests for Api\TranslationController.
 *
 * Endpoints tested:
 * - GET /api/articles/{id}/translations   (article translations)
 * - GET /api/categories/{id}/translations (category translations)
 * - GET /api/authors/{id}/translations    (author translations)
 *
 * These endpoints are under /api firewall. GET requests to articles/categories/authors
 * are PUBLIC_ACCESS per security.yaml.
 */
class TranslationControllerTest extends ApiTestCase
{
    private function createTestCategory(): Category
    {
        $em = static::getContainer()->get('doctrine')->getManager();

        $category = new Category();
        $category->setTitle('Translation Test Category');
        $category->setSlug('translation-test-category-' . uniqid());
        $em->persist($category);
        $em->flush();

        return $category;
    }

    private function createTestArticle(Category $category = null): Article
    {
        $em = static::getContainer()->get('doctrine')->getManager();

        if (!$category) {
            $category = $this->createTestCategory();
        }

        $author = new Author();
        $author->setFirstName('Translation');
        $author->setLastName('Test');
        $author->setEmail('translation-test-' . uniqid() . '@example.com');
        $author->setSlug('translation-test-author-' . uniqid());
        $em->persist($author);

        $article = new Article();
        $article->setTitle('Translation Test Article');
        $article->setSlug('translation-test-article-' . uniqid());
        $article->setLead('Test lead');
        $article->setContent('Test content');
        $article->setStatus(ArticleStatus::PUBLISHED);
        $article->setPublishedAt(new DateTimeImmutable());
        $article->setCategory($category);
        $article->addAuthor($author);
        $em->persist($article);
        $em->flush();

        return $article;
    }

    private function createTestAuthor(): Author
    {
        $em = static::getContainer()->get('doctrine')->getManager();

        $author = new Author();
        $author->setFirstName('Translation');
        $author->setLastName('Author');
        $author->setEmail('translation-author-' . uniqid() . '@example.com');
        $author->setSlug('translation-author-' . uniqid());
        $em->persist($author);
        $em->flush();

        return $author;
    }

    // =============================================
    // GET /api/articles/{id}/translations
    // =============================================

    public function testGetArticleTranslationsForExistingArticle(): void
    {
        $client = static::createClient();

        $article = $this->createTestArticle();

        $client->request('GET', '/api/articles/' . $article->getId() . '/translations');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals($article->getId(), $data['article_id']);
        $this->assertArrayHasKey('translations', $data);
        $this->assertIsArray($data['translations']);
        // Should have at least 'ro' locale
        $this->assertArrayHasKey('ro', $data['translations']);
    }

    public function testGetArticleTranslationsStructure(): void
    {
        $client = static::createClient();

        $article = $this->createTestArticle();

        $client->request('GET', '/api/articles/' . $article->getId() . '/translations');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        // Check translation structure for default locale
        $roTranslation = $data['translations']['ro'];
        $this->assertArrayHasKey('article_slug', $roTranslation);
        $this->assertArrayHasKey('category_slug', $roTranslation);
        $this->assertArrayHasKey('category_id', $roTranslation);
    }

    public function testGetArticleTranslationsForNonExistentArticle(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/articles/999999/translations');

        $this->assertResponseStatusCodeSame(404);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Article not found', $data['error']);
    }

    public function testGetArticleTranslationsIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/articles/1/translations');

        // Should NOT return 401
        $this->assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    // =============================================
    // GET /api/categories/{id}/translations
    // =============================================

    public function testGetCategoryTranslationsForExistingCategory(): void
    {
        $client = static::createClient();

        $category = $this->createTestCategory();

        $client->request('GET', '/api/categories/' . $category->getId() . '/translations');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals($category->getId(), $data['category_id']);
        $this->assertArrayHasKey('translations', $data);
        $this->assertArrayHasKey('ro', $data['translations']);
    }

    public function testGetCategoryTranslationsStructure(): void
    {
        $client = static::createClient();

        $category = $this->createTestCategory();

        $client->request('GET', '/api/categories/' . $category->getId() . '/translations');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        $roTranslation = $data['translations']['ro'];
        $this->assertArrayHasKey('category_slug', $roTranslation);
    }

    public function testGetCategoryTranslationsForNonExistentCategory(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/categories/999999/translations');

        $this->assertResponseStatusCodeSame(404);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Category not found', $data['error']);
    }

    public function testGetCategoryTranslationsIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/categories/1/translations');

        $this->assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    // =============================================
    // GET /api/authors/{id}/translations
    // =============================================

    public function testGetAuthorTranslationsForExistingAuthor(): void
    {
        $client = static::createClient();

        $author = $this->createTestAuthor();

        $client->request('GET', '/api/authors/' . $author->getId() . '/translations');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);
        $this->assertEquals($author->getId(), $data['author_id']);
        $this->assertArrayHasKey('translations', $data);
        // Authors are not translatable - same slug for all locales
        $this->assertArrayHasKey('ro', $data['translations']);
        $this->assertArrayHasKey('en', $data['translations']);
        $this->assertArrayHasKey('ru', $data['translations']);
        $this->assertArrayHasKey('note', $data);
    }

    public function testGetAuthorTranslationsHasSameSlugAllLocales(): void
    {
        $client = static::createClient();

        $author = $this->createTestAuthor();

        $client->request('GET', '/api/authors/' . $author->getId() . '/translations');

        $this->assertResponseIsSuccessful();

        $data = json_decode($client->getResponse()->getContent(), true);

        // Authors are not translatable, all locales should have same slug
        $roSlug = $data['translations']['ro']['author_slug'];
        $enSlug = $data['translations']['en']['author_slug'];
        $ruSlug = $data['translations']['ru']['author_slug'];

        $this->assertEquals($roSlug, $enSlug);
        $this->assertEquals($enSlug, $ruSlug);
    }

    public function testGetAuthorTranslationsForNonExistentAuthor(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/authors/999999/translations');

        $this->assertResponseStatusCodeSame(404);

        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);
        $this->assertEquals('Author not found', $data['error']);
    }

    public function testGetAuthorTranslationsIsPublic(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/authors/1/translations');

        $this->assertNotEquals(401, $client->getResponse()->getStatusCode());
    }

    // =============================================
    // HTTP method tests
    // =============================================

    public function testArticleTranslationsRejectsPostMethod(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/articles/1/translations');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testCategoryTranslationsRejectsPostMethod(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/categories/1/translations');

        $this->assertResponseStatusCodeSame(405);
    }

    public function testAuthorTranslationsRejectsPostMethod(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/authors/1/translations');

        $this->assertResponseStatusCodeSame(405);
    }
}
