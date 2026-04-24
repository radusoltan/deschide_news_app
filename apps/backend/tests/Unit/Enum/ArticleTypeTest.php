<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\ArticleType;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for {@see ArticleType} (Sprint 55 T55.2, ADR-020 D6).
 */
class ArticleTypeTest extends TestCase
{
    public function testAllCases(): void
    {
        $cases = ArticleType::cases();

        $this->assertCount(4, $cases);
        $this->assertContains(ArticleType::FLASH, $cases);
        $this->assertContains(ArticleType::DEVELOPING_STORY, $cases);
        $this->assertContains(ArticleType::LONGFORM, $cases);
        $this->assertContains(ArticleType::FULL_FLASH, $cases);
    }

    public function testValues(): void
    {
        $this->assertSame('flash', ArticleType::FLASH->value);
        $this->assertSame('developing_story', ArticleType::DEVELOPING_STORY->value);
        $this->assertSame('longform', ArticleType::LONGFORM->value);
        $this->assertSame('full_flash', ArticleType::FULL_FLASH->value);
    }

    public function testTryFromInvalidValueReturnsNull(): void
    {
        $this->assertNull(ArticleType::tryFrom('draft'));
    }

    public function testFromInvalidValueThrows(): void
    {
        $this->expectException(\ValueError::class);
        ArticleType::from('draft');
    }
}
