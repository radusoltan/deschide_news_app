<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\ThumbnailCategory;
use PHPUnit\Framework\TestCase;

class ThumbnailCategoryTest extends TestCase
{
    public function testAllCases(): void
    {
        $cases = ThumbnailCategory::cases();
        $this->assertCount(4, $cases);
    }

    public function testValues(): void
    {
        $this->assertSame('article', ThumbnailCategory::ARTICLE->value);
        $this->assertSame('profile', ThumbnailCategory::PROFILE->value);
        $this->assertSame('social', ThumbnailCategory::SOCIAL->value);
        $this->assertSame('general', ThumbnailCategory::GENERAL->value);
    }

    public function testFromValidValue(): void
    {
        $this->assertSame(ThumbnailCategory::ARTICLE, ThumbnailCategory::from('article'));
        $this->assertSame(ThumbnailCategory::PROFILE, ThumbnailCategory::from('profile'));
        $this->assertSame(ThumbnailCategory::SOCIAL, ThumbnailCategory::from('social'));
        $this->assertSame(ThumbnailCategory::GENERAL, ThumbnailCategory::from('general'));
    }

    public function testFromInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        ThumbnailCategory::from('invalid');
    }

    public function testTryFromValidValue(): void
    {
        $this->assertSame(ThumbnailCategory::ARTICLE, ThumbnailCategory::tryFrom('article'));
        $this->assertSame(ThumbnailCategory::PROFILE, ThumbnailCategory::tryFrom('profile'));
        $this->assertSame(ThumbnailCategory::SOCIAL, ThumbnailCategory::tryFrom('social'));
        $this->assertSame(ThumbnailCategory::GENERAL, ThumbnailCategory::tryFrom('general'));
    }

    public function testTryFromInvalidValue(): void
    {
        $this->assertNull(ThumbnailCategory::tryFrom('avatar'));
    }
}
