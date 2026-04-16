<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\BriefingCadence;
use PHPUnit\Framework\TestCase;

class BriefingCadenceTest extends TestCase
{
    public function testAllCases(): void
    {
        $cases = BriefingCadence::cases();
        $this->assertCount(3, $cases);
    }

    public function testValues(): void
    {
        $this->assertSame('hourly', BriefingCadence::HOURLY->value);
        $this->assertSame('daily', BriefingCadence::DAILY->value);
        $this->assertSame('weekly', BriefingCadence::WEEKLY->value);
    }

    public function testFromValidValue(): void
    {
        $this->assertSame(BriefingCadence::HOURLY, BriefingCadence::from('hourly'));
        $this->assertSame(BriefingCadence::DAILY, BriefingCadence::from('daily'));
        $this->assertSame(BriefingCadence::WEEKLY, BriefingCadence::from('weekly'));
    }

    public function testFromInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        BriefingCadence::from('biweekly');
    }

    public function testTryFromValidValue(): void
    {
        $this->assertSame(BriefingCadence::DAILY, BriefingCadence::tryFrom('daily'));
    }

    public function testTryFromInvalidValue(): void
    {
        $this->assertNull(BriefingCadence::tryFrom('invalid'));
    }
}
