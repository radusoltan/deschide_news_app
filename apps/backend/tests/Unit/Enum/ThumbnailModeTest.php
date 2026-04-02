<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\ThumbnailMode;
use PHPUnit\Framework\TestCase;

class ThumbnailModeTest extends TestCase
{
    public function testAllCases(): void
    {
        $cases = ThumbnailMode::cases();
        $this->assertCount(6, $cases);
    }

    public function testValues(): void
    {
        $this->assertSame('cover', ThumbnailMode::COVER->value);
        $this->assertSame('contain', ThumbnailMode::CONTAIN->value);
        $this->assertSame('crop', ThumbnailMode::CROP->value);
        $this->assertSame('scale', ThumbnailMode::SCALE->value);
        $this->assertSame('fit', ThumbnailMode::FIT->value);
        $this->assertSame('fill', ThumbnailMode::FILL->value);
    }

    public function testFromValidValue(): void
    {
        $this->assertSame(ThumbnailMode::COVER, ThumbnailMode::from('cover'));
        $this->assertSame(ThumbnailMode::CONTAIN, ThumbnailMode::from('contain'));
        $this->assertSame(ThumbnailMode::CROP, ThumbnailMode::from('crop'));
        $this->assertSame(ThumbnailMode::SCALE, ThumbnailMode::from('scale'));
        $this->assertSame(ThumbnailMode::FIT, ThumbnailMode::from('fit'));
        $this->assertSame(ThumbnailMode::FILL, ThumbnailMode::from('fill'));
    }

    public function testFromInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        ThumbnailMode::from('invalid');
    }

    public function testTryFromValidValue(): void
    {
        $this->assertSame(ThumbnailMode::COVER, ThumbnailMode::tryFrom('cover'));
        $this->assertSame(ThumbnailMode::CONTAIN, ThumbnailMode::tryFrom('contain'));
        $this->assertSame(ThumbnailMode::CROP, ThumbnailMode::tryFrom('crop'));
        $this->assertSame(ThumbnailMode::SCALE, ThumbnailMode::tryFrom('scale'));
        $this->assertSame(ThumbnailMode::FIT, ThumbnailMode::tryFrom('fit'));
        $this->assertSame(ThumbnailMode::FILL, ThumbnailMode::tryFrom('fill'));
    }

    public function testTryFromInvalidValue(): void
    {
        $this->assertNull(ThumbnailMode::tryFrom('stretch'));
    }
}
