<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Article;
use App\Entity\Category;
use App\Enum\ArticleStatus;
use App\Enum\CategoryStatus;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Category entity.
 *
 * Tests entity creation, getters, setters, and basic validation logic
 * according to Symfony and API Platform testing best practices.
 */
class CategoryTest extends TestCase
{
    public function testCategoryCreation(): void
    {
        $category = new Category();

        $this->assertNull($category->getId());
        $this->assertInstanceOf(Category::class, $category);
    }

    public function testSetAndGetTitle(): void
    {
        $category = new Category();
        $title = 'Politics';

        $result = $category->setTitle($title);

        $this->assertSame($category, $result); // Test fluent interface
        $this->assertEquals($title, $category->getTitle());
    }

    public function testSetAndGetSlug(): void
    {
        $category = new Category();
        $slug = 'politics';

        $result = $category->setSlug($slug);

        $this->assertSame($category, $result); // Test fluent interface
        $this->assertEquals($slug, $category->getSlug());
    }

    public function testDefaultStatus(): void
    {
        $category = new Category();

        // Default status should be ACTIVE
        $this->assertEquals(CategoryStatus::ACTIVE, $category->getStatus());
    }

    public function testSetAndGetStatus(): void
    {
        $category = new Category();

        $result = $category->setStatus(CategoryStatus::INACTIVE);

        $this->assertSame($category, $result);
        $this->assertEquals(CategoryStatus::INACTIVE, $category->getStatus());
    }

    public function testOnFrontPageDefaultValue(): void
    {
        $category = new Category();

        // Default should be false
        $this->assertFalse($category->isOnFrontPage());
    }

    public function testSetAndGetOnFrontPage(): void
    {
        $category = new Category();

        $result = $category->setOnFrontPage(true);

        $this->assertSame($category, $result);
        $this->assertTrue($category->isOnFrontPage());

        $category->setOnFrontPage(false);
        $this->assertFalse($category->isOnFrontPage());
    }

    public function testArticlesCollectionInitialization(): void
    {
        $category = new Category();

        // Articles collection should be initialized and empty
        $this->assertCount(0, $category->getArticles());
    }

    public function testAddArticle(): void
    {
        $category = new Category();
        $article = $this->createMock(Article::class);

        $article->expects($this->once())
            ->method('setCategory')
            ->with($category);

        $result = $category->addArticle($article);

        $this->assertSame($category, $result);
        $this->assertCount(1, $category->getArticles());
        $this->assertTrue($category->getArticles()->contains($article));
    }

    public function testAddArticleDoesNotAddDuplicates(): void
    {
        $category = new Category();
        $article = $this->createMock(Article::class);

        $article->expects($this->once()) // Should only be called once
            ->method('setCategory')
            ->with($category);

        $category->addArticle($article);
        $category->addArticle($article); // Add same article again

        $this->assertCount(1, $category->getArticles()); // Should still be 1
    }

    public function testRemoveArticle(): void
    {
        $category = new Category();
        $article = $this->createMock(Article::class);

        $article->method('setCategory');
        $article->expects($this->once())
            ->method('getCategory')
            ->willReturn($category);

        $category->addArticle($article);
        $this->assertCount(1, $category->getArticles());

        $result = $category->removeArticle($article);

        $this->assertSame($category, $result);
        $this->assertCount(0, $category->getArticles());
        $this->assertFalse($category->getArticles()->contains($article));
    }

    public function testTranslatableLocale(): void
    {
        $category = new Category();

        $this->assertNull($category->getLocale());

        $category->setTranslatableLocale('ro');
        $this->assertEquals('ro', $category->getLocale());

        $category->setTranslatableLocale('en');
        $this->assertEquals('en', $category->getLocale());

        $category->setTranslatableLocale('ru');
        $this->assertEquals('ru', $category->getLocale());
    }

    public function testCreatedAtTimestamp(): void
    {
        $category = new Category();

        // Initially null (will be set by Gedmo on persist)
        $this->assertNull($category->getCreatedAt());
    }

    public function testUpdatedAtTimestamp(): void
    {
        $category = new Category();

        // Initially null (will be set by Gedmo on persist/update)
        $this->assertNull($category->getUpdatedAt());
    }

    /**
     * Test that CategoryStatus enum has all expected cases.
     */
    public function testCategoryStatusEnumCases(): void
    {
        $cases = CategoryStatus::cases();

        $this->assertGreaterThanOrEqual(2, \count($cases));
        $this->assertContains(CategoryStatus::ACTIVE, $cases);
        $this->assertContains(CategoryStatus::INACTIVE, $cases);
    }

    /**
     * Test that title can be set to maximum allowed length (255 chars).
     */
    public function testTitleMaxLength(): void
    {
        $category = new Category();
        $longTitle = str_repeat('a', 255);

        $category->setTitle($longTitle);

        $this->assertEquals(255, \strlen($category->getTitle()));
        $this->assertEquals($longTitle, $category->getTitle());
    }

    /**
     * Test slug max length (255 chars).
     */
    public function testSlugMaxLength(): void
    {
        $category = new Category();
        $longSlug = str_repeat('a', 255);

        $category->setSlug($longSlug);

        $this->assertEquals(255, \strlen($category->getSlug()));
    }

    /**
     * Test fluent interface for all setters.
     */
    public function testFluentInterface(): void
    {
        $category = new Category();

        $result = $category
            ->setTitle('Technology')
            ->setSlug('technology')
            ->setStatus(CategoryStatus::ACTIVE)
            ->setOnFrontPage(true);

        $this->assertSame($category, $result);
        $this->assertEquals('Technology', $category->getTitle());
        $this->assertEquals('technology', $category->getSlug());
        $this->assertEquals(CategoryStatus::ACTIVE, $category->getStatus());
        $this->assertTrue($category->isOnFrontPage());
    }

    /**
     * Test getArticleCount method filters only published articles.
     */
    public function testGetArticleCountFiltersPublishedArticles(): void
    {
        $category = new Category();

        // Create published article
        $publishedArticle = $this->createMock(Article::class);
        $publishedArticle->method('getStatus')->willReturn(ArticleStatus::PUBLISHED);
        $publishedArticle->method('setCategory');

        // Create non-published article (NEW status)
        $newArticle = $this->createMock(Article::class);
        $newArticle->method('getStatus')->willReturn(ArticleStatus::NEW);
        $newArticle->method('setCategory');

        $category->addArticle($publishedArticle);
        $category->addArticle($newArticle);

        // getArticleCount should only count published articles
        $this->assertEquals(1, $category->getArticleCount());
    }
}
