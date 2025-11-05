<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Article;
use App\Entity\ArticleImage;
use App\Entity\Image;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ArticleImage entity (pivot entity).
 *
 * Tests the association between Articles and Images with additional metadata.
 */
class ArticleImageTest extends TestCase
{
    public function testArticleImageCreation(): void
    {
        $articleImage = new ArticleImage();

        $this->assertNull($articleImage->getId());
        $this->assertInstanceOf(ArticleImage::class, $articleImage);
    }

    public function testConstructorWithParameters(): void
    {
        $article = $this->createMock(Article::class);
        $image = $this->createMock(Image::class);

        $articleImage = new ArticleImage($article, $image, 1, true);

        $this->assertSame($article, $articleImage->getArticle());
        $this->assertSame($image, $articleImage->getImage());
        $this->assertEquals(1, $articleImage->getPosition());
        $this->assertTrue($articleImage->isFeatured());
    }

    public function testConstructorWithDefaults(): void
    {
        $articleImage = new ArticleImage();

        $this->assertNull($articleImage->getArticle());
        $this->assertNull($articleImage->getImage());
        $this->assertEquals(0, $articleImage->getPosition());
        $this->assertFalse($articleImage->isFeatured());
    }

    public function testSetAndGetArticle(): void
    {
        $articleImage = new ArticleImage();
        $article = $this->createMock(Article::class);

        $result = $articleImage->setArticle($article);

        $this->assertSame($articleImage, $result); // Test fluent interface
        $this->assertSame($article, $articleImage->getArticle());
    }

    public function testSetAndGetImage(): void
    {
        $articleImage = new ArticleImage();
        $image = $this->createMock(Image::class);

        $result = $articleImage->setImage($image);

        $this->assertSame($articleImage, $result);
        $this->assertSame($image, $articleImage->getImage());
    }

    public function testSetAndGetPosition(): void
    {
        $articleImage = new ArticleImage();

        $result = $articleImage->setPosition(5);

        $this->assertSame($articleImage, $result);
        $this->assertEquals(5, $articleImage->getPosition());
    }

    public function testDefaultPosition(): void
    {
        $articleImage = new ArticleImage();

        $this->assertEquals(0, $articleImage->getPosition());
    }

    public function testSetAndGetIsFeatured(): void
    {
        $articleImage = new ArticleImage();

        $result = $articleImage->setIsFeatured(true);

        $this->assertSame($articleImage, $result);
        $this->assertTrue($articleImage->isFeatured());

        $articleImage->setIsFeatured(false);
        $this->assertFalse($articleImage->isFeatured());
    }

    public function testDefaultIsFeatured(): void
    {
        $articleImage = new ArticleImage();

        $this->assertFalse($articleImage->isFeatured());
    }

    public function testCreatedAtTimestamp(): void
    {
        $articleImage = new ArticleImage();

        // Initially null (will be set by Gedmo on persist)
        $this->assertNull($articleImage->getCreatedAt());
    }

    public function testFluentInterface(): void
    {
        $articleImage = new ArticleImage();
        $article = $this->createMock(Article::class);
        $image = $this->createMock(Image::class);

        $result = $articleImage
            ->setArticle($article)
            ->setImage($image)
            ->setPosition(3)
            ->setIsFeatured(true);

        $this->assertSame($articleImage, $result);
        $this->assertSame($article, $articleImage->getArticle());
        $this->assertSame($image, $articleImage->getImage());
        $this->assertEquals(3, $articleImage->getPosition());
        $this->assertTrue($articleImage->isFeatured());
    }

    public function testPositionCanBeZero(): void
    {
        $articleImage = new ArticleImage();

        $articleImage->setPosition(0);

        $this->assertEquals(0, $articleImage->getPosition());
    }

    public function testPositionCanBePositiveInteger(): void
    {
        $articleImage = new ArticleImage();

        $articleImage->setPosition(100);

        $this->assertEquals(100, $articleImage->getPosition());
    }
}
