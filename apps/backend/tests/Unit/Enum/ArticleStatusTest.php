<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\ArticleStatus;
use PHPUnit\Framework\TestCase;

class ArticleStatusTest extends TestCase
{
    public function testAllCases(): void
    {
        $cases = ArticleStatus::cases();
        $this->assertCount(5, $cases);
    }

    public function testValues(): void
    {
        $this->assertSame('new', ArticleStatus::NEW->value);
        $this->assertSame('submitted', ArticleStatus::SUBMITTED->value);
        $this->assertSame('published', ArticleStatus::PUBLISHED->value);
        $this->assertSame('published_full', ArticleStatus::PUBLISHED_FULL->value);
        $this->assertSame('archived', ArticleStatus::ARCHIVED->value);
    }

    public function testFromValidValue(): void
    {
        $this->assertSame(ArticleStatus::NEW, ArticleStatus::from('new'));
        $this->assertSame(ArticleStatus::SUBMITTED, ArticleStatus::from('submitted'));
        $this->assertSame(ArticleStatus::PUBLISHED, ArticleStatus::from('published'));
        $this->assertSame(ArticleStatus::PUBLISHED_FULL, ArticleStatus::from('published_full'));
        $this->assertSame(ArticleStatus::ARCHIVED, ArticleStatus::from('archived'));
    }

    public function testFromInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        ArticleStatus::from('invalid');
    }

    public function testTryFromValidValue(): void
    {
        $this->assertSame(ArticleStatus::PUBLISHED, ArticleStatus::tryFrom('published'));
    }

    public function testTryFromInvalidValue(): void
    {
        $this->assertNull(ArticleStatus::tryFrom('invalid'));
    }
}
