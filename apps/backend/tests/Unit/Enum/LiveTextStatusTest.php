<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\LiveTextStatus;
use PHPUnit\Framework\TestCase;

class LiveTextStatusTest extends TestCase
{
    public function testAllCases(): void
    {
        $cases = LiveTextStatus::cases();
        $this->assertCount(4, $cases);
    }

    public function testValues(): void
    {
        $this->assertSame('draft', LiveTextStatus::DRAFT->value);
        $this->assertSame('live', LiveTextStatus::LIVE->value);
        $this->assertSame('paused', LiveTextStatus::PAUSED->value);
        $this->assertSame('ended', LiveTextStatus::ENDED->value);
    }

    public function testFromValidValue(): void
    {
        $this->assertSame(LiveTextStatus::DRAFT, LiveTextStatus::from('draft'));
        $this->assertSame(LiveTextStatus::LIVE, LiveTextStatus::from('live'));
        $this->assertSame(LiveTextStatus::PAUSED, LiveTextStatus::from('paused'));
        $this->assertSame(LiveTextStatus::ENDED, LiveTextStatus::from('ended'));
    }

    public function testFromInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        LiveTextStatus::from('invalid');
    }

    public function testTryFromValidValue(): void
    {
        $this->assertSame(LiveTextStatus::DRAFT, LiveTextStatus::tryFrom('draft'));
        $this->assertSame(LiveTextStatus::LIVE, LiveTextStatus::tryFrom('live'));
        $this->assertSame(LiveTextStatus::PAUSED, LiveTextStatus::tryFrom('paused'));
        $this->assertSame(LiveTextStatus::ENDED, LiveTextStatus::tryFrom('ended'));
    }

    public function testTryFromInvalidValue(): void
    {
        $this->assertNull(LiveTextStatus::tryFrom('stopped'));
    }
}
