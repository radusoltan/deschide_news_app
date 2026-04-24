<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto\Aggregator;

use App\Dto\Aggregator\RemoteContentResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RemoteContentResult::class)]
class RemoteContentResultTest extends TestCase
{
    public function testConstructionAndProperties(): void
    {
        $result = new RemoteContentResult(
            title: 'Article Title',
            textContent: 'Plain text content',
            htmlContent: '<p>Plain text content</p>',
            excerpt: 'A short excerpt',
            siteName: 'Example News',
            imageUrl: 'https://example.com/image.jpg',
            wordCount: 150,
        );

        self::assertSame('Article Title', $result->title);
        self::assertSame('Plain text content', $result->textContent);
        self::assertSame('<p>Plain text content</p>', $result->htmlContent);
        self::assertSame('A short excerpt', $result->excerpt);
        self::assertSame('Example News', $result->siteName);
        self::assertSame('https://example.com/image.jpg', $result->imageUrl);
        self::assertSame(150, $result->wordCount);
    }

    public function testNullableFields(): void
    {
        $result = new RemoteContentResult(
            title: 'Title',
            textContent: 'Content',
            htmlContent: '<p>Content</p>',
            excerpt: null,
            siteName: null,
            imageUrl: null,
            wordCount: 5,
        );

        self::assertNull($result->excerpt);
        self::assertNull($result->siteName);
        self::assertNull($result->imageUrl);
    }
}
