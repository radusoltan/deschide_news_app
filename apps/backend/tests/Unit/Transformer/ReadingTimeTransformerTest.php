<?php

declare(strict_types=1);

namespace App\Tests\Unit\Transformer;

use App\Entity\Article;
use App\Transformer\Article\ReadingTimeTransformer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;

#[\PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations]
class ReadingTimeTransformerTest extends TestCase
{
    private ReadingTimeTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new ReadingTimeTransformer();
    }

    #[Test]
    public function itReturnsNullForNonArticleSource(): void
    {
        $result = ($this->transformer)(null, new stdClass(), null);

        $this->assertNull($result);
    }

    #[Test]
    public function itReturnsNullForArticleWithNoContent(): void
    {
        $article = $this->createMock(Article::class);
        $article->method('getContent')->willReturn(null);

        $result = ($this->transformer)(null, $article, null);

        $this->assertNull($result);
    }

    #[Test]
    public function itReturnsNullForArticleWithEmptyContent(): void
    {
        $article = $this->createMock(Article::class);
        $article->method('getContent')->willReturn('');

        $result = ($this->transformer)(null, $article, null);

        $this->assertNull($result);
    }

    #[Test]
    public function itCalculatesOneMinuteForShortContent(): void
    {
        $article = $this->createMock(Article::class);
        // 50 words = 1 minute (ceil(50/200) = 1)
        $article->method('getContent')->willReturn(
            str_repeat('word ', 50)
        );

        $result = ($this->transformer)(null, $article, null);

        $this->assertSame(1, $result);
    }

    #[Test]
    public function itCalculatesTwoMinutesFor400Words(): void
    {
        $article = $this->createMock(Article::class);
        // 400 words = 2 minutes (ceil(400/200) = 2)
        $article->method('getContent')->willReturn(
            str_repeat('word ', 400)
        );

        $result = ($this->transformer)(null, $article, null);

        $this->assertSame(2, $result);
    }

    #[Test]
    public function itStripHtmlTagsBeforeCountingWords(): void
    {
        $article = $this->createMock(Article::class);
        $article->method('getContent')->willReturn(
            '<p>This is a <strong>test</strong> article with <em>HTML</em> tags.</p>'
        );

        $result = ($this->transformer)(null, $article, null);

        // "This is a test article with HTML tags" = 8 words => ceil(8/200) = 1
        $this->assertSame(1, $result);
    }

    #[Test]
    public function itCalculatesCorrectReadingTimeForLargeContent(): void
    {
        $article = $this->createMock(Article::class);
        // 1000 words = 5 minutes (ceil(1000/200) = 5)
        $article->method('getContent')->willReturn(
            str_repeat('word ', 1000)
        );

        $result = ($this->transformer)(null, $article, null);

        $this->assertSame(5, $result);
    }

    #[Test]
    public function itRoundsUpForPartialMinute(): void
    {
        $article = $this->createMock(Article::class);
        // 201 words = 2 minutes (ceil(201/200) = 2)
        $article->method('getContent')->willReturn(
            str_repeat('word ', 201)
        );

        $result = ($this->transformer)(null, $article, null);

        $this->assertSame(2, $result);
    }
}
