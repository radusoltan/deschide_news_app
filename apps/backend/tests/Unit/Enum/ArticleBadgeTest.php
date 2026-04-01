<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\ArticleBadge;
use PHPUnit\Framework\TestCase;

class ArticleBadgeTest extends TestCase
{
    public function testAllCases(): void
    {
        $cases = ArticleBadge::cases();
        $this->assertCount(3, $cases);
    }

    public function testValues(): void
    {
        $this->assertSame('breaking', ArticleBadge::BREAKING->value);
        $this->assertSame('alert', ArticleBadge::ALERT->value);
        $this->assertSame('flash', ArticleBadge::FLASH->value);
    }

    public function testFromValidValue(): void
    {
        $this->assertSame(ArticleBadge::BREAKING, ArticleBadge::from('breaking'));
        $this->assertSame(ArticleBadge::ALERT, ArticleBadge::from('alert'));
        $this->assertSame(ArticleBadge::FLASH, ArticleBadge::from('flash'));
    }

    public function testFromInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        ArticleBadge::from('invalid');
    }

    public function testTryFromValidValue(): void
    {
        $this->assertSame(ArticleBadge::BREAKING, ArticleBadge::tryFrom('breaking'));
        $this->assertSame(ArticleBadge::ALERT, ArticleBadge::tryFrom('alert'));
        $this->assertSame(ArticleBadge::FLASH, ArticleBadge::tryFrom('flash'));
    }

    public function testTryFromInvalidValue(): void
    {
        $this->assertNull(ArticleBadge::tryFrom('nonexistent'));
    }
}
