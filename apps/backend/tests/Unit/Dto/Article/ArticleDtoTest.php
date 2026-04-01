<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto\Article;

use App\Dto\Article\ArticleInput;
use App\Dto\Article\ArticleListOutput;
use App\Dto\Article\ArticleOutput;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ArticleDtoTest extends TestCase
{
    #[Test]
    public function articleOutputHasCorrectDefaults(): void
    {
        $dto = new ArticleOutput();

        $this->assertNull($dto->id);
        $this->assertNull($dto->title);
        $this->assertNull($dto->slug);
        $this->assertNull($dto->lead);
        $this->assertNull($dto->content);
        $this->assertNull($dto->status);
        $this->assertNull($dto->badge);
        $this->assertFalse($dto->featured);
        $this->assertSame(0, $dto->viewCount);
        $this->assertNull($dto->createdAt);
        $this->assertNull($dto->updatedAt);
        $this->assertNull($dto->publishedAt);
        $this->assertNull($dto->locale);
        $this->assertNull($dto->readingTime);
    }

    #[Test]
    public function articleOutputCanSetProperties(): void
    {
        $dto = new ArticleOutput();
        $now = new DateTimeImmutable();

        $dto->id = 42;
        $dto->title = 'Test Title';
        $dto->slug = 'test-title';
        $dto->lead = 'Test lead';
        $dto->content = 'Test content';
        $dto->status = 'published';
        $dto->badge = 'breaking';
        $dto->featured = true;
        $dto->viewCount = 100;
        $dto->createdAt = $now;
        $dto->updatedAt = $now;
        $dto->publishedAt = $now;
        $dto->locale = 'ro';
        $dto->readingTime = 3;

        $this->assertSame(42, $dto->id);
        $this->assertSame('Test Title', $dto->title);
        $this->assertSame('test-title', $dto->slug);
        $this->assertSame('Test lead', $dto->lead);
        $this->assertSame('Test content', $dto->content);
        $this->assertSame('published', $dto->status);
        $this->assertSame('breaking', $dto->badge);
        $this->assertTrue($dto->featured);
        $this->assertSame(100, $dto->viewCount);
        $this->assertSame($now, $dto->createdAt);
        $this->assertSame($now, $dto->updatedAt);
        $this->assertSame($now, $dto->publishedAt);
        $this->assertSame('ro', $dto->locale);
        $this->assertSame(3, $dto->readingTime);
    }

    #[Test]
    public function articleListOutputHasCorrectDefaults(): void
    {
        $dto = new ArticleListOutput();

        $this->assertNull($dto->id);
        $this->assertNull($dto->title);
        $this->assertNull($dto->slug);
        $this->assertNull($dto->lead);
        $this->assertNull($dto->status);
        $this->assertNull($dto->badge);
        $this->assertFalse($dto->featured);
        $this->assertSame(0, $dto->viewCount);
        $this->assertNull($dto->publishedAt);
        $this->assertNull($dto->locale);
        $this->assertNull($dto->readingTime);
    }

    #[Test]
    public function articleListOutputCanSetProperties(): void
    {
        $dto = new ArticleListOutput();
        $now = new DateTimeImmutable();

        $dto->id = 99;
        $dto->title = 'List Article';
        $dto->slug = 'list-article';
        $dto->lead = 'Some lead';
        $dto->status = 'new';
        $dto->badge = 'alert';
        $dto->featured = true;
        $dto->viewCount = 50;
        $dto->publishedAt = $now;
        $dto->locale = 'en';
        $dto->readingTime = 5;

        $this->assertSame(99, $dto->id);
        $this->assertSame('List Article', $dto->title);
        $this->assertSame('list-article', $dto->slug);
        $this->assertSame('Some lead', $dto->lead);
        $this->assertSame('new', $dto->status);
        $this->assertSame('alert', $dto->badge);
        $this->assertTrue($dto->featured);
        $this->assertSame(50, $dto->viewCount);
        $this->assertSame($now, $dto->publishedAt);
        $this->assertSame('en', $dto->locale);
        $this->assertSame(5, $dto->readingTime);
    }

    #[Test]
    public function articleInputHasCorrectDefaults(): void
    {
        $dto = new ArticleInput();

        $this->assertNull($dto->title);
        $this->assertNull($dto->lead);
        $this->assertNull($dto->content);
        $this->assertSame('new', $dto->status);
        $this->assertNull($dto->badge);
        $this->assertFalse($dto->featured);
    }

    #[Test]
    public function articleInputCanSetProperties(): void
    {
        $dto = new ArticleInput();

        $dto->title = 'New Article';
        $dto->lead = 'The lead paragraph';
        $dto->content = 'Full article content';
        $dto->status = 'published';
        $dto->badge = 'flash';
        $dto->featured = true;

        $this->assertSame('New Article', $dto->title);
        $this->assertSame('The lead paragraph', $dto->lead);
        $this->assertSame('Full article content', $dto->content);
        $this->assertSame('published', $dto->status);
        $this->assertSame('flash', $dto->badge);
        $this->assertTrue($dto->featured);
    }
}
