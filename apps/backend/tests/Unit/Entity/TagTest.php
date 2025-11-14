<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Article;
use App\Entity\Tag;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Tag entity.
 *
 * Tests entity creation, getters, setters, and basic validation logic
 * according to Symfony and API Platform testing best practices.
 */
class TagTest extends TestCase
{
    public function testTagCreation(): void
    {
        $tag = new Tag();

        $this->assertNull($tag->getId());
        $this->assertInstanceOf(Tag::class, $tag);
    }

    public function testSetAndGetName(): void
    {
        $tag = new Tag();
        $name = 'Politics';

        $result = $tag->setName($name);

        $this->assertSame($tag, $result); // Test fluent interface
        $this->assertEquals($name, $tag->getName());
    }

    public function testSetAndGetSlug(): void
    {
        $tag = new Tag();
        $slug = 'politics';

        $result = $tag->setSlug($slug);

        $this->assertSame($tag, $result); // Test fluent interface
        $this->assertEquals($slug, $tag->getSlug());
    }

    public function testSetAndGetDescription(): void
    {
        $tag = new Tag();
        $description = 'Articles about politics and government';

        $result = $tag->setDescription($description);

        $this->assertSame($tag, $result);
        $this->assertEquals($description, $tag->getDescription());
    }

    public function testDefaultUsageCount(): void
    {
        $tag = new Tag();

        // Default usage count should be 0
        $this->assertEquals(0, $tag->getUsageCount());
    }

    public function testSetAndGetUsageCount(): void
    {
        $tag = new Tag();

        $result = $tag->setUsageCount(10);

        $this->assertSame($tag, $result);
        $this->assertEquals(10, $tag->getUsageCount());
    }

    public function testArticlesCollectionInitialization(): void
    {
        $tag = new Tag();

        // Articles collection should be initialized and empty
        $this->assertCount(0, $tag->getArticles());
    }

    public function testAddArticle(): void
    {
        $tag = new Tag();
        $article = $this->createMock(Article::class);

        $article->expects($this->once())
            ->method('addTag')
            ->with($tag);

        $result = $tag->addArticle($article);

        $this->assertSame($tag, $result);
        $this->assertCount(1, $tag->getArticles());
        $this->assertTrue($tag->getArticles()->contains($article));
    }

    public function testAddArticleDoesNotAddDuplicates(): void
    {
        $tag = new Tag();
        $article = $this->createMock(Article::class);

        $article->expects($this->once()) // Should only be called once
            ->method('addTag')
            ->with($tag);

        $tag->addArticle($article);
        $tag->addArticle($article); // Add same article again

        $this->assertCount(1, $tag->getArticles()); // Should still be 1
    }

    public function testRemoveArticle(): void
    {
        $tag = new Tag();
        $article = $this->createMock(Article::class);

        $article->method('addTag');
        $article->expects($this->once())
            ->method('removeTag')
            ->with($tag);

        $tag->addArticle($article);
        $this->assertCount(1, $tag->getArticles());

        $result = $tag->removeArticle($article);

        $this->assertSame($tag, $result);
        $this->assertCount(0, $tag->getArticles());
        $this->assertFalse($tag->getArticles()->contains($article));
    }

    public function testTranslatableLocale(): void
    {
        $tag = new Tag();

        $this->assertNull($tag->getLocale());

        $tag->setTranslatableLocale('ro');
        $this->assertEquals('ro', $tag->getLocale());

        $tag->setTranslatableLocale('en');
        $this->assertEquals('en', $tag->getLocale());

        $tag->setTranslatableLocale('ru');
        $this->assertEquals('ru', $tag->getLocale());
    }

    public function testCreatedAtTimestamp(): void
    {
        $tag = new Tag();

        // Initially null (will be set by Gedmo on persist)
        $this->assertNull($tag->getCreatedAt());
    }

    public function testUpdatedAtTimestamp(): void
    {
        $tag = new Tag();

        // Initially null (will be set by Gedmo on persist/update)
        $this->assertNull($tag->getUpdatedAt());
    }

    /**
     * Test that name can be set to maximum allowed length (100 chars).
     */
    public function testNameMaxLength(): void
    {
        $tag = new Tag();
        $longName = str_repeat('a', 100);

        $tag->setName($longName);

        $this->assertEquals(100, \strlen($tag->getName()));
        $this->assertEquals($longName, $tag->getName());
    }

    /**
     * Test slug max length (100 chars).
     */
    public function testSlugMaxLength(): void
    {
        $tag = new Tag();
        $longSlug = str_repeat('a', 100);

        $tag->setSlug($longSlug);

        $this->assertEquals(100, \strlen($tag->getSlug()));
    }

    /**
     * Test fluent interface for all setters.
     */
    public function testFluentInterface(): void
    {
        $tag = new Tag();

        $result = $tag
            ->setName('Technology')
            ->setSlug('technology')
            ->setDescription('Tech articles')
            ->setUsageCount(5);

        $this->assertSame($tag, $result);
        $this->assertEquals('Technology', $tag->getName());
        $this->assertEquals('technology', $tag->getSlug());
        $this->assertEquals('Tech articles', $tag->getDescription());
        $this->assertEquals(5, $tag->getUsageCount());
    }

    /**
     * Test description can be null.
     */
    public function testDescriptionCanBeNull(): void
    {
        $tag = new Tag();

        $this->assertNull($tag->getDescription());

        $tag->setDescription('Test description');
        $this->assertEquals('Test description', $tag->getDescription());

        $tag->setDescription(null);
        $this->assertNull($tag->getDescription());
    }

    /**
     * Test usage count increment and decrement.
     */
    public function testUsageCountManipulation(): void
    {
        $tag = new Tag();

        $this->assertEquals(0, $tag->getUsageCount());

        // Increment
        $tag->setUsageCount($tag->getUsageCount() + 1);
        $this->assertEquals(1, $tag->getUsageCount());

        $tag->setUsageCount($tag->getUsageCount() + 1);
        $this->assertEquals(2, $tag->getUsageCount());

        // Decrement
        $tag->setUsageCount($tag->getUsageCount() - 1);
        $this->assertEquals(1, $tag->getUsageCount());

        // Should not go below zero
        $tag->setUsageCount(max(0, $tag->getUsageCount() - 10));
        $this->assertEquals(0, $tag->getUsageCount());
    }

    /**
     * Test that empty name can be detected.
     */
    public function testEmptyName(): void
    {
        $tag = new Tag();

        $this->assertNull($tag->getName());

        $tag->setName('');
        $this->assertEquals('', $tag->getName());
        $this->assertTrue(empty($tag->getName()));
    }
}
