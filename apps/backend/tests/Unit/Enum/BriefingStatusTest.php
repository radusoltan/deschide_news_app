<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\BriefingStatus;
use PHPUnit\Framework\TestCase;

class BriefingStatusTest extends TestCase
{
    public function testAllCases(): void
    {
        $cases = BriefingStatus::cases();
        $this->assertCount(6, $cases);
    }

    public function testValues(): void
    {
        $this->assertSame('pending', BriefingStatus::PENDING->value);
        $this->assertSame('generating', BriefingStatus::GENERATING->value);
        $this->assertSame('draft', BriefingStatus::DRAFT->value);
        $this->assertSame('polished', BriefingStatus::POLISHED->value);
        $this->assertSame('published', BriefingStatus::PUBLISHED->value);
        $this->assertSame('failed', BriefingStatus::FAILED->value);
    }

    public function testFromValidValue(): void
    {
        $this->assertSame(BriefingStatus::PENDING, BriefingStatus::from('pending'));
        $this->assertSame(BriefingStatus::GENERATING, BriefingStatus::from('generating'));
        $this->assertSame(BriefingStatus::DRAFT, BriefingStatus::from('draft'));
        $this->assertSame(BriefingStatus::POLISHED, BriefingStatus::from('polished'));
        $this->assertSame(BriefingStatus::PUBLISHED, BriefingStatus::from('published'));
        $this->assertSame(BriefingStatus::FAILED, BriefingStatus::from('failed'));
    }

    public function testFromInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        BriefingStatus::from('invalid');
    }

    public function testTryFromValidValue(): void
    {
        $this->assertSame(BriefingStatus::DRAFT, BriefingStatus::tryFrom('draft'));
    }

    public function testTryFromInvalidValue(): void
    {
        $this->assertNull(BriefingStatus::tryFrom('invalid'));
    }
}
