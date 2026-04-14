<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cleaning;

use App\Service\Cleaning\NewsmakerCleaner;
use PHPUnit\Framework\TestCase;

class NewsmakerCleanerTest extends TestCase
{
    private NewsmakerCleaner $cleaner;

    protected function setUp(): void
    {
        $this->cleaner = new NewsmakerCleaner();
    }

    public function testSupportsNewsmakerSources(): void
    {
        $this->assertTrue($this->cleaner->supports('scrape:newsmaker_md'));
        $this->assertTrue($this->cleaner->supports('scrape:newsmaker'));
        $this->assertFalse($this->cleaner->supports('scrape:agerpres'));
        $this->assertFalse($this->cleaner->supports('scrape:zugo_md'));
    }

    public function testRemovesSkipNavigation(): void
    {
        $content = '<p>Sari la conținut</p><p>Actual article text here.</p>';
        $result = $this->cleaner->clean($content);

        $this->assertStringNotContainsString('Sari la conținut', $result);
        $this->assertStringContainsString('Actual article text here', $result);
    }

    public function testRemovesByline(): void
    {
        $content = '<p>- Ana-Maria Dolghii - | 12 aprilie, 2026 - 10:29</p><p>Article body.</p>';
        $result = $this->cleaner->clean($content);

        $this->assertStringNotContainsString('Ana-Maria Dolghii', $result);
        $this->assertStringContainsString('Article body', $result);
    }

    public function testTruncatesAtMistapeBoundary(): void
    {
        $content = '<p>Article content here.</p><p>More article content.</p>'
            . '<p><a href="https://mistape.com">If you found a spelling error</a></p>'
            . '<p>Related article 1</p><p>Related article 2</p>';
        $result = $this->cleaner->clean($content);

        $this->assertStringContainsString('Article content here', $result);
        $this->assertStringContainsString('More article content', $result);
        $this->assertStringNotContainsString('mistape.com', $result);
        $this->assertStringNotContainsString('Related article 1', $result);
    }

    public function testTruncatesAtNmEspressoBoundary(): void
    {
        $content = '<p>Article text.</p><p>Abonați-vă la NM Espresso</p><p>Noise after.</p>';
        $result = $this->cleaner->clean($content);

        $this->assertStringContainsString('Article text', $result);
        $this->assertStringNotContainsString('NM Espresso', $result);
        $this->assertStringNotContainsString('Noise after', $result);
    }

    public function testRemovesTelegramPromo(): void
    {
        $content = '<p>Article text.</p>'
            . '<p>Abonați-vă la canalul nostru de Telegram pentru știri rapide newsmakerlive</p>'
            . '<p>More article.</p>';
        $result = $this->cleaner->clean($content);

        $this->assertStringNotContainsString('Telegram', $result);
        $this->assertStringContainsString('Article text', $result);
        $this->assertStringContainsString('More article', $result);
    }

    public function testRemovesDonationBlock(): void
    {
        $content = '<p>Article text.</p>'
            . '<p>Doriți să susțineți jurnalismul independent? Susține NewsMaker!</p>'
            . '<p>More article.</p>';
        $result = $this->cleaner->clean($content);

        $this->assertStringNotContainsString('Doriți să susțineți', $result);
        $this->assertStringContainsString('Article text', $result);
    }

    public function testCleanContentPassedThroughUnchanged(): void
    {
        $content = '<p>Un articol simplu despre economia Moldovei.</p>'
            . '<p>Autoritățile au raportat o creștere de 5% a PIB-ului.</p>';
        $result = $this->cleaner->clean($content);

        $this->assertStringContainsString('economia Moldovei', $result);
        $this->assertStringContainsString('5% a PIB-ului', $result);
    }

    public function testFullNoisyContentCleaning(): void
    {
        // Simulate real Newsmaker content structure
        $content = '<p>Sari la conținut</p>'
            . '<p>Reuters</p>'
            . '<p>- Război în Ucraina</p>'
            . '<p>Titlul articolului</p>'
            . '<p>- Ana-Maria Dolghii - | 12 aprilie, 2026 - 12:33</p>'
            . '<p>**Textul principal al articolului cu informații importante.**</p>'
            . '<p>Al doilea paragraf cu detalii suplimentare.</p>'
            . '<p>[](https://mistape.com)If you have found a spelling error</p>'
            . '<p>- tag1, tag2, top</p>'
            . '<p>Abonați-vă la NM Espresso</p>'
            . '<p>Related article title here</p>'
            . '<p>Locuri de muncă în Chișinău</p>';
        $result = $this->cleaner->clean($content);

        // Article body should be preserved
        $this->assertStringContainsString('Textul principal al articolului', $result);
        $this->assertStringContainsString('Al doilea paragraf', $result);

        // All noise should be removed
        $this->assertStringNotContainsString('Sari la conținut', $result);
        $this->assertStringNotContainsString('mistape.com', $result);
        $this->assertStringNotContainsString('NM Espresso', $result);
        $this->assertStringNotContainsString('Locuri de muncă', $result);
    }
}
