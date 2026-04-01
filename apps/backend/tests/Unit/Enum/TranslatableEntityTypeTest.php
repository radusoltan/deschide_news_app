<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\TranslatableEntityType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TranslatableEntityType::class)]
class TranslatableEntityTypeTest extends TestCase
{
    #[Test]
    public function enumValues(): void
    {
        $this->assertSame('category', TranslatableEntityType::CATEGORY->value);
        $this->assertSame('author', TranslatableEntityType::AUTHOR->value);
    }

    #[Test]
    public function tryFromValidStrings(): void
    {
        $this->assertSame(TranslatableEntityType::CATEGORY, TranslatableEntityType::tryFrom('category'));
        $this->assertSame(TranslatableEntityType::AUTHOR, TranslatableEntityType::tryFrom('author'));
    }

    #[Test]
    public function tryFromInvalidReturnsNull(): void
    {
        $this->assertNull(TranslatableEntityType::tryFrom('article'));
        $this->assertNull(TranslatableEntityType::tryFrom(''));
    }

    #[Test]
    public function casesReturnsAllValues(): void
    {
        $cases = TranslatableEntityType::cases();
        $this->assertCount(2, $cases);
    }
}
