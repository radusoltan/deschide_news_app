<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\SourceType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SourceTypeTest extends TestCase
{
    #[Test]
    public function allValuesAreBackedStrings(): void
    {
        $this->assertSame('email', SourceType::EMAIL->value);
        $this->assertSame('scrape', SourceType::SCRAPE->value);
        $this->assertSame('manual', SourceType::MANUAL->value);
        $this->assertSame('aggregator', SourceType::AGGREGATOR->value);
    }

    #[Test]
    public function canBeCreatedFromString(): void
    {
        $this->assertSame(SourceType::EMAIL, SourceType::from('email'));
        $this->assertSame(SourceType::SCRAPE, SourceType::from('scrape'));
        $this->assertSame(SourceType::MANUAL, SourceType::from('manual'));
    }

    #[Test]
    public function casesReturnsAllValues(): void
    {
        $cases = SourceType::cases();
        $this->assertCount(4, $cases);
    }
}
