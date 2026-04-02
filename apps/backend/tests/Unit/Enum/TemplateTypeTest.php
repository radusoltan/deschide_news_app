<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\TemplateType;
use PHPUnit\Framework\TestCase;

class TemplateTypeTest extends TestCase
{
    public function testAllCases(): void
    {
        $cases = TemplateType::cases();
        $this->assertCount(4, $cases);
    }

    public function testValues(): void
    {
        $this->assertSame('breaking_news', TemplateType::BREAKING_NEWS->value);
        $this->assertSame('sport', TemplateType::SPORT->value);
        $this->assertSame('conference', TemplateType::CONFERENCE->value);
        $this->assertSame('election', TemplateType::ELECTION->value);
    }

    public function testStaticValues(): void
    {
        $values = TemplateType::values();
        $this->assertCount(4, $values);
        $this->assertContains('breaking_news', $values);
        $this->assertContains('sport', $values);
        $this->assertContains('conference', $values);
        $this->assertContains('election', $values);
    }

    public function testGetLabelDefaultLocale(): void
    {
        $this->assertStringContainsString('Ultimă Oră', TemplateType::BREAKING_NEWS->getLabel());
        $this->assertStringContainsString('Sportiv', TemplateType::SPORT->getLabel());
        $this->assertStringContainsString('Conferință', TemplateType::CONFERENCE->getLabel());
        $this->assertStringContainsString('Alegeri', TemplateType::ELECTION->getLabel());
    }

    public function testGetLabelEnglish(): void
    {
        $this->assertSame('Breaking News', TemplateType::BREAKING_NEWS->getLabel('en'));
        $this->assertSame('Sport Event', TemplateType::SPORT->getLabel('en'));
        $this->assertSame('Conference', TemplateType::CONFERENCE->getLabel('en'));
        $this->assertSame('Election', TemplateType::ELECTION->getLabel('en'));
    }

    public function testGetLabelRussian(): void
    {
        $this->assertNotEmpty(TemplateType::BREAKING_NEWS->getLabel('ru'));
        $this->assertNotEmpty(TemplateType::SPORT->getLabel('ru'));
        $this->assertNotEmpty(TemplateType::CONFERENCE->getLabel('ru'));
        $this->assertNotEmpty(TemplateType::ELECTION->getLabel('ru'));
    }

    public function testGetDescriptionDefaultLocale(): void
    {
        $this->assertNotEmpty(TemplateType::BREAKING_NEWS->getDescription());
        $this->assertNotEmpty(TemplateType::SPORT->getDescription());
        $this->assertNotEmpty(TemplateType::CONFERENCE->getDescription());
        $this->assertNotEmpty(TemplateType::ELECTION->getDescription());
    }

    public function testGetDescriptionEnglish(): void
    {
        $this->assertStringContainsString('urgent', TemplateType::BREAKING_NEWS->getDescription('en'));
        $this->assertStringContainsString('sports', TemplateType::SPORT->getDescription('en'));
        $this->assertStringContainsString('conferences', TemplateType::CONFERENCE->getDescription('en'));
        $this->assertStringContainsString('election', TemplateType::ELECTION->getDescription('en'));
    }

    public function testGetDescriptionRussian(): void
    {
        $this->assertStringContainsString('срочных', TemplateType::BREAKING_NEWS->getDescription('ru'));
        $this->assertStringContainsString('спортивных', TemplateType::SPORT->getDescription('ru'));
        $this->assertStringContainsString('конференций', TemplateType::CONFERENCE->getDescription('ru'));
        $this->assertStringContainsString('выборов', TemplateType::ELECTION->getDescription('ru'));
    }

    public function testGetIcon(): void
    {
        $this->assertNotEmpty(TemplateType::BREAKING_NEWS->getIcon());
        $this->assertNotEmpty(TemplateType::SPORT->getIcon());
        $this->assertNotEmpty(TemplateType::CONFERENCE->getIcon());
        $this->assertNotEmpty(TemplateType::ELECTION->getIcon());
    }

    public function testGetIconSpecificValues(): void
    {
        $this->assertSame("\u{1F6A8}", TemplateType::BREAKING_NEWS->getIcon());
        $this->assertSame("\u{26BD}", TemplateType::SPORT->getIcon());
        $this->assertSame("\u{1F3A4}", TemplateType::CONFERENCE->getIcon());
        $this->assertSame("\u{1F5F3}\u{FE0F}", TemplateType::ELECTION->getIcon());
    }

    public function testFromValidValue(): void
    {
        $this->assertSame(TemplateType::BREAKING_NEWS, TemplateType::from('breaking_news'));
        $this->assertSame(TemplateType::ELECTION, TemplateType::from('election'));
    }

    public function testFromInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        TemplateType::from('invalid');
    }
}
