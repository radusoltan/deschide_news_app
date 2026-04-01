<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\NotificationImportance;
use PHPUnit\Framework\TestCase;

class NotificationImportanceTest extends TestCase
{
    public function testAllCases(): void
    {
        $cases = NotificationImportance::cases();
        $this->assertCount(4, $cases);
    }

    public function testValues(): void
    {
        $this->assertSame('low', NotificationImportance::LOW->value);
        $this->assertSame('medium', NotificationImportance::MEDIUM->value);
        $this->assertSame('high', NotificationImportance::HIGH->value);
        $this->assertSame('urgent', NotificationImportance::URGENT->value);
    }

    public function testFromValidValue(): void
    {
        $this->assertSame(NotificationImportance::LOW, NotificationImportance::from('low'));
        $this->assertSame(NotificationImportance::URGENT, NotificationImportance::from('urgent'));
    }

    public function testTryFromValidValue(): void
    {
        $this->assertSame(NotificationImportance::LOW, NotificationImportance::tryFrom('low'));
        $this->assertSame(NotificationImportance::MEDIUM, NotificationImportance::tryFrom('medium'));
        $this->assertSame(NotificationImportance::HIGH, NotificationImportance::tryFrom('high'));
        $this->assertSame(NotificationImportance::URGENT, NotificationImportance::tryFrom('urgent'));
    }

    public function testFromInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        NotificationImportance::from('invalid');
    }

    public function testTryFromInvalidValue(): void
    {
        $this->assertNull(NotificationImportance::tryFrom('critical'));
    }
}
