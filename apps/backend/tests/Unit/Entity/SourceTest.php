<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Source;
use App\Enum\SourceCategory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SourceTest extends TestCase
{
    #[Test]
    public function itInitializesWithDefaults(): void
    {
        $source = new Source();

        $this->assertNull($source->getId());
        $this->assertSame(0.5, $source->getCredibilityWeight());
        $this->assertNull($source->getCountry());
        $this->assertNull($source->getSourceCategory());
        $this->assertSame(60, $source->getFetchFrequencyMinutes());
        $this->assertTrue($source->isActive());
        $this->assertSame('rss', $source->getType());
        $this->assertNull($source->getRssUrl());
        $this->assertNull($source->getDomainPattern());
        $this->assertInstanceOf(\DateTimeImmutable::class, $source->getCreatedAt());
    }

    #[Test]
    public function itSetsAndGetsName(): void
    {
        $source = new Source();
        $result = $source->setName('Reuters');

        $this->assertSame('Reuters', $source->getName());
        $this->assertSame($source, $result);
    }

    #[Test]
    public function itSetsAndGetsCredibilityWeight(): void
    {
        $source = new Source();
        $source->setCredibilityWeight(0.95);

        $this->assertSame(0.95, $source->getCredibilityWeight());
    }

    #[Test]
    public function itSetsAndGetsCountry(): void
    {
        $source = new Source();
        $source->setCountry('GB');

        $this->assertSame('GB', $source->getCountry());
    }

    #[Test]
    public function itSetsAndGetsSourceCategory(): void
    {
        $source = new Source();
        $source->setSourceCategory(SourceCategory::AGENCY);

        $this->assertSame(SourceCategory::AGENCY, $source->getSourceCategory());
    }

    #[Test]
    public function itSetsAndGetsRssUrl(): void
    {
        $source = new Source();
        $source->setRssUrl('https://www.reuters.com/arc/outboundfeeds/v3/all/rss.xml');

        $this->assertSame('https://www.reuters.com/arc/outboundfeeds/v3/all/rss.xml', $source->getRssUrl());
    }

    #[Test]
    public function itSetsAndGetsDomainPattern(): void
    {
        $source = new Source();
        $source->setDomainPattern('reuters.com');

        $this->assertSame('reuters.com', $source->getDomainPattern());
    }

    #[Test]
    public function itSetsAndGetsIsActive(): void
    {
        $source = new Source();
        $source->setIsActive(false);

        $this->assertFalse($source->isActive());
    }
}
