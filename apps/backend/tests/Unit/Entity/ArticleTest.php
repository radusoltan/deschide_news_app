<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Article;
use App\Entity\Author;
use App\Entity\Category;
use App\Enum\ArticleBadge;
use App\Enum\ArticleStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Article entity.
 *
 * Tests entity creation, getters, setters, relationships, and business logic
 * according to Symfony and API Platform testing best practices.
 */
class ArticleTest extends TestCase
{
    public function testArticleCreation(): void
    {
        $article = new Article();

        $this->assertNull($article->getId());
        $this->assertInstanceOf(Article::class, $article);
    }

    public function testSetAndGetTitle(): void
    {
        $article = new Article();
        $title = 'Breaking News: Important Event';

        $result = $article->setTitle($title);

        $this->assertSame($article, $result); // Test fluent interface
        $this->assertEquals($title, $article->getTitle());
    }

    public function testSetAndGetSlug(): void
    {
        $article = new Article();
        $slug = 'breaking-news-important-event';

        $result = $article->setSlug($slug);

        $this->assertSame($article, $result);
        $this->assertEquals($slug, $article->getSlug());
    }

    public function testSetAndGetLead(): void
    {
        $article = new Article();
        $lead = 'This is the article lead paragraph with a summary.';

        $result = $article->setLead($lead);

        $this->assertSame($article, $result);
        $this->assertEquals($lead, $article->getLead());
    }

    public function testLeadCanBeNull(): void
    {
        $article = new Article();

        $article->setLead(null);

        $this->assertNull($article->getLead());
    }

    public function testSetAndGetContent(): void
    {
        $article = new Article();
        $content = 'This is the full article content with multiple paragraphs.';

        $result = $article->setContent($content);

        $this->assertSame($article, $result);
        $this->assertEquals($content, $article->getContent());
    }

    // Category Tests

    public function testSetAndGetCategory(): void
    {
        $article = new Article();
        $category = $this->createMock(Category::class);

        $result = $article->setCategory($category);

        $this->assertSame($article, $result);
        $this->assertSame($category, $article->getCategory());
    }

    public function testCategoryCanBeNull(): void
    {
        $article = new Article();

        $article->setCategory(null);

        $this->assertNull($article->getCategory());
    }

    // Authors Tests (ManyToMany)

    public function testAuthorsCollectionInitialization(): void
    {
        $article = new Article();

        $this->assertCount(0, $article->getAuthors());
    }

    public function testAddAuthor(): void
    {
        $article = new Article();
        $author = $this->createMock(Author::class);

        $result = $article->addAuthor($author);

        $this->assertSame($article, $result);
        $this->assertCount(1, $article->getAuthors());
        $this->assertTrue($article->getAuthors()->contains($author));
    }

    public function testAddAuthorDoesNotAddDuplicates(): void
    {
        $article = new Article();
        $author = $this->createMock(Author::class);

        $article->addAuthor($author);
        $article->addAuthor($author); // Add same author again

        $this->assertCount(1, $article->getAuthors()); // Should still be 1
    }

    public function testRemoveAuthor(): void
    {
        $article = new Article();
        $author = $this->createMock(Author::class);

        $article->addAuthor($author);
        $this->assertCount(1, $article->getAuthors());

        $result = $article->removeAuthor($author);

        $this->assertSame($article, $result);
        $this->assertCount(0, $article->getAuthors());
        $this->assertFalse($article->getAuthors()->contains($author));
    }

    // Status Tests

    public function testDefaultStatus(): void
    {
        $article = new Article();

        // Default status should be NEW
        $this->assertEquals(ArticleStatus::NEW, $article->getStatus());
    }

    public function testSetAndGetStatus(): void
    {
        $article = new Article();

        $article->setStatus(ArticleStatus::PUBLISHED);
        $this->assertEquals(ArticleStatus::PUBLISHED, $article->getStatus());

        $article->setStatus(ArticleStatus::SUBMITTED);
        $this->assertEquals(ArticleStatus::SUBMITTED, $article->getStatus());
    }

    // Badge Tests

    public function testBadgeCanBeNull(): void
    {
        $article = new Article();

        $this->assertNull($article->getBadge());
    }

    public function testSetAndGetBadge(): void
    {
        $article = new Article();

        $article->setBadge(ArticleBadge::BREAKING);
        $this->assertEquals(ArticleBadge::BREAKING, $article->getBadge());

        $article->setBadge(ArticleBadge::ALERT);
        $this->assertEquals(ArticleBadge::ALERT, $article->getBadge());

        $article->setBadge(ArticleBadge::FLASH);
        $this->assertEquals(ArticleBadge::FLASH, $article->getBadge());

        $article->setBadge(null);
        $this->assertNull($article->getBadge());
    }

    // Featured Tests

    public function testIsFeaturedDefaultValue(): void
    {
        $article = new Article();

        // Default should be false
        $this->assertFalse($article->isFeatured());
    }

    public function testSetAndGetIsFeatured(): void
    {
        $article = new Article();

        $result = $article->setIsFeatured(true);

        $this->assertSame($article, $result);
        $this->assertTrue($article->isFeatured());

        $article->setIsFeatured(false);
        $this->assertFalse($article->isFeatured());
    }

    // View Count Tests

    public function testViewCountDefaultValue(): void
    {
        $article = new Article();

        // Default should be 0
        $this->assertEquals(0, $article->getViewCount());
    }

    public function testSetAndGetViewCount(): void
    {
        $article = new Article();

        $result = $article->setViewCount(100);

        $this->assertSame($article, $result);
        $this->assertEquals(100, $article->getViewCount());
    }

    // Timestamp Tests

    public function testCreatedAtTimestamp(): void
    {
        $article = new Article();

        // Initially null (will be set by Gedmo on persist)
        $this->assertNull($article->getCreatedAt());
    }

    public function testUpdatedAtTimestamp(): void
    {
        $article = new Article();

        // Initially null (will be set by Gedmo on persist/update)
        $this->assertNull($article->getUpdatedAt());
    }

    public function testPublishedAtTimestamp(): void
    {
        $article = new Article();

        $this->assertNull($article->getPublishedAt());

        $now = new DateTimeImmutable();
        $result = $article->setPublishedAt($now);

        $this->assertSame($article, $result);
        $this->assertEquals($now, $article->getPublishedAt());
    }

    public function testPublishAtTimestamp(): void
    {
        $article = new Article();

        $this->assertNull($article->getPublishAt());

        $future = new DateTimeImmutable('+1 day');
        $result = $article->setPublishAt($future);

        $this->assertSame($article, $result);
        $this->assertEquals($future, $article->getPublishAt());
    }

    // Translatable Tests

    public function testTranslatableLocale(): void
    {
        $article = new Article();

        $this->assertNull($article->getLocale());

        $article->setTranslatableLocale('ro');
        $this->assertEquals('ro', $article->getLocale());

        $article->setTranslatableLocale('en');
        $this->assertEquals('en', $article->getLocale());

        $article->setTranslatableLocale('ru');
        $this->assertEquals('ru', $article->getLocale());
    }

    // Article Images Collection Tests

    public function testArticleImagesCollectionInitialization(): void
    {
        $article = new Article();

        // Collection should be initialized and empty
        $this->assertCount(0, $article->getArticleImages());
    }

    // Related Articles Collection Tests

    public function testRelatedArticlesCollectionInitialization(): void
    {
        $article = new Article();

        // Collection should be initialized and empty
        $this->assertCount(0, $article->getRelatedArticles());
    }

    public function testAddRelatedArticle(): void
    {
        $article = new Article();
        $relatedArticle = new Article();

        $result = $article->addRelatedArticle($relatedArticle);

        $this->assertSame($article, $result);
        $this->assertCount(1, $article->getRelatedArticles());
        $this->assertTrue($article->getRelatedArticles()->contains($relatedArticle));
    }

    public function testAddRelatedArticleDoesNotAddDuplicates(): void
    {
        $article = new Article();
        $relatedArticle = new Article();

        $article->addRelatedArticle($relatedArticle);
        $article->addRelatedArticle($relatedArticle); // Add same article again

        $this->assertCount(1, $article->getRelatedArticles()); // Should still be 1
    }

    public function testRemoveRelatedArticle(): void
    {
        $article = new Article();
        $relatedArticle = new Article();

        $article->addRelatedArticle($relatedArticle);
        $this->assertCount(1, $article->getRelatedArticles());

        $result = $article->removeRelatedArticle($relatedArticle);

        $this->assertSame($article, $result);
        $this->assertCount(0, $article->getRelatedArticles());
        $this->assertFalse($article->getRelatedArticles()->contains($relatedArticle));
    }

    // Computed Properties Tests

    public function testGetReadingTime(): void
    {
        $article = new Article();

        // With no content, reading time should be 0
        $this->assertEquals(0, $article->getReadingTime());

        // Set content (typical reading speed: ~200-250 words/minute)
        $words = str_repeat('word ', 500); // 500 words
        $article->setContent($words);

        // Should be approximately 2-3 minutes for 500 words
        $readingTime = $article->getReadingTime();
        $this->assertGreaterThan(0, $readingTime);
    }

    // Enum Tests

    public function testArticleStatusEnumCases(): void
    {
        $cases = ArticleStatus::cases();

        $this->assertCount(3, $cases);
        $this->assertContains(ArticleStatus::NEW, $cases);
        $this->assertContains(ArticleStatus::SUBMITTED, $cases);
        $this->assertContains(ArticleStatus::PUBLISHED, $cases);
    }

    public function testArticleBadgeEnumCases(): void
    {
        $cases = ArticleBadge::cases();

        $this->assertCount(3, $cases);
        $this->assertContains(ArticleBadge::BREAKING, $cases);
        $this->assertContains(ArticleBadge::ALERT, $cases);
        $this->assertContains(ArticleBadge::FLASH, $cases);
    }

    // Max Length Tests

    public function testTitleMaxLength(): void
    {
        $article = new Article();
        $longTitle = str_repeat('a', 255);

        $article->setTitle($longTitle);

        $this->assertEquals(255, \strlen($article->getTitle()));
    }

    public function testLeadMaxLength(): void
    {
        $article = new Article();
        $longLead = str_repeat('a', 3000);

        $article->setLead($longLead);

        $this->assertEquals(3000, \strlen($article->getLead()));
    }

    // Fluent Interface Test

    public function testFluentInterface(): void
    {
        $article = new Article();
        $category = $this->createMock(Category::class);

        $result = $article
            ->setTitle('Test Article')
            ->setSlug('test-article')
            ->setLead('This is a lead')
            ->setContent('This is content')
            ->setCategory($category)
            ->setStatus(ArticleStatus::PUBLISHED)
            ->setBadge(ArticleBadge::BREAKING)
            ->setIsFeatured(true)
            ->setViewCount(100);

        $this->assertSame($article, $result);
        $this->assertEquals('Test Article', $article->getTitle());
        $this->assertEquals('test-article', $article->getSlug());
        $this->assertEquals('This is a lead', $article->getLead());
        $this->assertEquals('This is content', $article->getContent());
        $this->assertSame($category, $article->getCategory());
        $this->assertEquals(ArticleStatus::PUBLISHED, $article->getStatus());
        $this->assertEquals(ArticleBadge::BREAKING, $article->getBadge());
        $this->assertTrue($article->isFeatured());
        $this->assertEquals(100, $article->getViewCount());
    }
}
