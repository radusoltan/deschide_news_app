<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\CategoryStatus;
use PHPUnit\Framework\TestCase;

class CategoryStatusTest extends TestCase
{
    public function testAllCases(): void
    {
        $cases = CategoryStatus::cases();
        $this->assertCount(3, $cases);
    }

    public function testValues(): void
    {
        $this->assertSame('active', CategoryStatus::ACTIVE->value);
        $this->assertSame('inactive', CategoryStatus::INACTIVE->value);
        $this->assertSame('archived', CategoryStatus::ARCHIVED->value);
    }

    public function testFromValidValue(): void
    {
        $this->assertSame(CategoryStatus::ACTIVE, CategoryStatus::from('active'));
        $this->assertSame(CategoryStatus::INACTIVE, CategoryStatus::from('inactive'));
        $this->assertSame(CategoryStatus::ARCHIVED, CategoryStatus::from('archived'));
    }

    public function testFromInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        CategoryStatus::from('invalid');
    }

    public function testTryFromValidValue(): void
    {
        $this->assertSame(CategoryStatus::ACTIVE, CategoryStatus::tryFrom('active'));
        $this->assertSame(CategoryStatus::INACTIVE, CategoryStatus::tryFrom('inactive'));
        $this->assertSame(CategoryStatus::ARCHIVED, CategoryStatus::tryFrom('archived'));
    }

    public function testTryFromInvalidValue(): void
    {
        $this->assertNull(CategoryStatus::tryFrom('deleted'));
    }
}
