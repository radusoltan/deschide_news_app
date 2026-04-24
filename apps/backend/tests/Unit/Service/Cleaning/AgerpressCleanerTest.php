<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Cleaning;

use App\Service\Cleaning\AgerpressCleaner;
use PHPUnit\Framework\TestCase;

class AgerpressCleanerTest extends TestCase
{
    private AgerpressCleaner $cleaner;

    protected function setUp(): void
    {
        $this->cleaner = new AgerpressCleaner();
    }

    public function testSupportsAgerpressSources(): void
    {
        $this->assertTrue($this->cleaner->supports('scrape:agerpres'));
        $this->assertTrue($this->cleaner->supports('aggregator:Agerpres'));
        $this->assertFalse($this->cleaner->supports('scrape:newsmaker_md'));
    }

    public function testStripsHeaderBlock(): void
    {
        $content = '<p>Agerpres – Agenția Națională de Presă: Știri de actualitate</p>'
            . '<p>Articolul propriu-zis despre eveniment.</p>';
        $result = $this->cleaner->clean($content);

        $this->assertStringNotContainsString('Agenția Națională de Presă', $result);
        $this->assertStringContainsString('Articolul propriu-zis', $result);
    }

    public function testRemovesSocialShareLinks(): void
    {
        $content = '<p>Articolul.</p>'
            . '<p>Share on facebook.com/sharer and linkedin.com/shareArticle</p>'
            . '<p>Continuarea articolului.</p>';
        $result = $this->cleaner->clean($content);

        $this->assertStringNotContainsString('facebook.com/sharer', $result);
        $this->assertStringContainsString('Articolul', $result);
        $this->assertStringContainsString('Continuarea articolului', $result);
    }

    public function testTruncatesAtRelatedArticlesBoundary(): void
    {
        $content = '<p>Articolul principal.</p>'
            . '<p>Alte știri din categorie</p>'
            . '<p>Știre legată 1</p>'
            . '<p>Știre legată 2</p>';
        $result = $this->cleaner->clean($content);

        $this->assertStringContainsString('Articolul principal', $result);
        $this->assertStringNotContainsString('Alte știri din categorie', $result);
        $this->assertStringNotContainsString('Știre legată 1', $result);
    }

    public function testTruncatesAtCopyrightBoundary(): void
    {
        $content = '<p>Articolul principal.</p>'
            . '<p>Conținutul website-ului www.agerpres.ro este destinat informării publicului.</p>';
        $result = $this->cleaner->clean($content);

        $this->assertStringContainsString('Articolul principal', $result);
        $this->assertStringNotContainsString('Conținutul website-ului', $result);
    }

    public function testRemovesTimestampedArticleList(): void
    {
        $content = '<p>Articolul principal.</p>'
            . '<p>10:38 - Fotbal: Villareal învingătoare</p>'
            . '<p>10:33 - Nicușor Dan a felicitat</p>';
        $result = $this->cleaner->clean($content);

        $this->assertStringContainsString('Articolul principal', $result);
        $this->assertStringNotContainsString('Villareal', $result);
        $this->assertStringNotContainsString('Nicușor Dan', $result);
    }

    public function testCleanContentPassedThrough(): void
    {
        $content = '<p>Președintele a anunțat noi măsuri economice.</p>'
            . '<p>Aceste măsuri vor intra în vigoare din luna mai.</p>';
        $result = $this->cleaner->clean($content);

        $this->assertStringContainsString('noi măsuri economice', $result);
        $this->assertStringContainsString('luna mai', $result);
    }
}
