<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\AuthorStatus;
use PHPUnit\Framework\TestCase;

class AuthorStatusTest extends TestCase
{
    public function testAllCases(): void
    {
        $cases = AuthorStatus::cases();
        $this->assertCount(2, $cases);
    }

    public function testValues(): void
    {
        $this->assertSame('active', AuthorStatus::ACTIVE->value);
        $this->assertSame('inactive', AuthorStatus::INACTIVE->value);
    }

    public function testFromValidValue(): void
    {
        $this->assertSame(AuthorStatus::ACTIVE, AuthorStatus::from('active'));
        $this->assertSame(AuthorStatus::INACTIVE, AuthorStatus::from('inactive'));
    }

    public function testFromInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        AuthorStatus::from('invalid');
    }

    public function testTryFromValidValue(): void
    {
        $this->assertSame(AuthorStatus::ACTIVE, AuthorStatus::tryFrom('active'));
        $this->assertSame(AuthorStatus::INACTIVE, AuthorStatus::tryFrom('inactive'));
    }

    public function testTryFromInvalidValue(): void
    {
        $this->assertNull(AuthorStatus::tryFrom('unknown'));
    }
}
