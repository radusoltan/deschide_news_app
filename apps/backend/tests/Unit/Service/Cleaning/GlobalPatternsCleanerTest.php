<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cleaning;

use App\Service\Cleaning\GlobalPatternsCleaner;
use PHPUnit\Framework\TestCase;

class GlobalPatternsCleanerTest extends TestCase
{
    private GlobalPatternsCleaner $cleaner;

    protected function setUp(): void
    {
        $this->cleaner = new GlobalPatternsCleaner();
    }

    public function testSupportsAllSources(): void
    {
        $this->assertTrue($this->cleaner->supports('scrape:newsmaker_md'));
        $this->assertTrue($this->cleaner->supports('aggregator:Agerpres'));
        $this->assertTrue($this->cleaner->supports('any:source'));
    }

    public function testRemovesAiTranslationPreamble(): void
    {
        $content = "I will translate the article below.\n\nActual content here.";
        $result = $this->cleaner->clean($content);

        $this->assertStringNotContainsString('I will translate', $result);
        $this->assertStringContainsString('Actual content here', $result);
    }

    public function testRemovesReadAlsoLines(): void
    {
        $content = "Article text.\nCitește și: Alt articol interesant\nMore text.";
        $result = $this->cleaner->clean($content);

        $this->assertStringNotContainsString('Citește și', $result);
        $this->assertStringContainsString('Article text', $result);
        $this->assertStringContainsString('More text', $result);
    }

    public function testRemovesSpellingErrorReport(): void
    {
        $content = "Article text.\nSpelling error report\nThe following text will be sent.";
        $result = $this->cleaner->clean($content);

        $this->assertStringNotContainsString('Spelling error report', $result);
        $this->assertStringContainsString('Article text', $result);
    }

    public function testCleanContentPassedThrough(): void
    {
        $content = "Normal article content without any noise patterns.";
        $result = $this->cleaner->clean($content);

        $this->assertSame('Normal article content without any noise patterns.', $result);
    }
}
