<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\RomanianSlugger;
use PHPUnit\Framework\TestCase;

class RomanianSluggerTest extends TestCase
{
    private RomanianSlugger $slugger;

    protected function setUp(): void
    {
        $this->slugger = new RomanianSlugger();
    }

    public function testSlugifyRomanianS(): void
    {
        $this->assertSame('stiri-locale', $this->slugger->slugify('Știri Locale'));
    }

    public function testSlugifyRomanianT(): void
    {
        $result = $this->slugger->slugify('Țara mea');
        $this->assertStringContainsString('ara', $result);
        $this->assertStringNotContainsString('Ț', $result);
    }

    public function testSlugifyRomanianSAndT(): void
    {
        $result = $this->slugger->slugify('Șeful Țării');
        $this->assertStringNotContainsString('Ș', $result);
        $this->assertStringNotContainsString('Ț', $result);
        $this->assertStringNotContainsString(' ', $result);
    }

    public function testSlugifyRomanianAWithBreve(): void
    {
        $result = $this->slugger->slugify('Ăsta e bun');
        $this->assertStringNotContainsString('Ă', $result);
    }

    public function testSlugifyRomanianAWithCircumflex(): void
    {
        $result = $this->slugger->slugify('Întâmplări din România');
        $this->assertStringNotContainsString('â', $result);
        $this->assertStringNotContainsString('î', $result);
        $this->assertStringNotContainsString('Î', $result);
    }

    public function testSlugifyRomanianIWithCircumflex(): void
    {
        $result = $this->slugger->slugify('Începutul');
        $this->assertStringNotContainsString('Î', $result);
        $this->assertStringNotContainsString('î', $result);
    }

    public function testSlugifyUsesHyphenAsSeparator(): void
    {
        $result = $this->slugger->slugify('Hello World');
        $this->assertSame('hello-world', $result);
    }

    public function testSlugifyWithCustomSeparator(): void
    {
        $result = $this->slugger->slugify('Hello World', '_');
        $this->assertSame('hello_world', $result);
    }

    public function testSlugifyRemovesSpecialCharacters(): void
    {
        $result = $this->slugger->slugify('Hello! @World#');
        $this->assertStringNotContainsString('!', $result);
        $this->assertStringNotContainsString('@', $result);
        $this->assertStringNotContainsString('#', $result);
    }

    public function testSlugifyConvertsToLowercase(): void
    {
        $result = $this->slugger->slugify('HELLO WORLD');
        $this->assertSame('hello-world', $result);
    }

    public function testSlugifyEmptyString(): void
    {
        $result = $this->slugger->slugify('');
        $this->assertSame('', $result);
    }

    public function testSlugifyOnlySpaces(): void
    {
        $result = $this->slugger->slugify('   ');
        $this->assertSame('', $result);
    }

    public function testSlugifyStaticMethod(): void
    {
        $result = RomanianSlugger::slugifyStatic('Știri Locale');
        $this->assertSame('stiri-locale', $result);
    }

    public function testSlugifyStaticWithCustomSeparator(): void
    {
        $result = RomanianSlugger::slugifyStatic('Hello World', '_');
        $this->assertSame('hello_world', $result);
    }

    public function testSlugifyStaticMatchesInstanceMethod(): void
    {
        $text = 'Întâmplări din România cu Ș și Ț';
        $instanceResult = $this->slugger->slugify($text);
        $staticResult = RomanianSlugger::slugifyStatic($text);
        $this->assertSame($instanceResult, $staticResult);
    }

    public function testSlugifyWithNumbers(): void
    {
        $result = $this->slugger->slugify('Article 123 Title');
        $this->assertStringContainsString('123', $result);
    }

    public function testSlugifyWithMultipleSpaces(): void
    {
        $result = $this->slugger->slugify('Hello    World');
        $this->assertStringNotContainsString('--', $result);
    }
}
