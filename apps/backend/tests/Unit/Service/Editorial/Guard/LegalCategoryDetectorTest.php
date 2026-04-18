<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Guard;

use App\Service\Editorial\Guard\LegalCategoryDetector;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for {@see LegalCategoryDetector} (Sprint 55 T55.7).
 */
class LegalCategoryDetectorTest extends TestCase
{
    private LegalCategoryDetector $detector;

    protected function setUp(): void
    {
        $this->detector = new LegalCategoryDetector();
    }

    public function testEmptyTextIsNotCategory6(): void
    {
        $this->assertFalse($this->detector->isCategory6(''));
    }

    public function testKeywordAlonePlusRoleOnlyReferenceIsNotCategory6(): void
    {
        // Role reference stays lowercase — no proper-noun indicator. The lone
        // keyword should NOT trip the detector by itself.
        $text = 'Un deputat este acuzat de implicare într-o schemă, conform unor surse.';

        $this->assertFalse($this->detector->isCategory6($text));
    }

    public function testCapitalisedNameAloneIsNotCategory6(): void
    {
        $text = 'Ion Popescu a vizitat orașul și a mulțumit primăriei pentru organizare.';

        $this->assertFalse($this->detector->isCategory6($text));
    }

    public function testAccusationKeywordWithProperNounIsCategory6(): void
    {
        $text = 'Ion Popescu este acuzat de trafic de persoane, potrivit unor surse.';

        $this->assertTrue($this->detector->isCategory6($text));
    }

    public function testMultipleAccusationKeywordsWithoutNameAreStillCategory6(): void
    {
        // Two distinct accusation-keyword matches inside the 50-word window
        // satisfy the ≥2-indicator rule even without a proper-noun match.
        $text = 'Fostul ministru este acuzat de abuz sexual. Un alt oficial este suspectat de corupție în același dosar.';

        $this->assertTrue($this->detector->isCategory6($text));
    }

    public function testAbbreviationsLikeCECAreNotTreatedAsPersonName(): void
    {
        // Pure uppercase abbreviations (CEC, NATO, UE) should NOT count as
        // proper-noun indicators, so a single keyword + CEC doesn't trigger.
        $text = 'Un oficial este acuzat de abuz sexual după audierile de la CEC.';

        $this->assertTrue(
            $this->detector->isCategory6($text),
            'Two accusation keywords in-window (acuzat de + abuz sexual) trigger',
        );
    }

    public function testRoutineCoverageWithoutAccusationKeywordsIsNotCategory6(): void
    {
        $text = 'Ion Popescu, deputat în noul mandat, a depus jurământul. A mulțumit alegătorilor '
            . 'din Chișinău și a anunțat programul legislativ pe care îl va promova în sesiunea următoare.';

        $this->assertFalse($this->detector->isCategory6($text));
    }

    public function testCourtConvictionLanguageWithoutAccusationKeywordsIsNotCategory6(): void
    {
        // Negative case: verified court ruling uses neutral factual language.
        $text = 'Instanța supremă a menținut hotărârea pronunțată în primă instanță. '
            . 'Sentința este definitivă, iar condamnatul începe executarea.';

        $this->assertFalse($this->detector->isCategory6($text));
    }

    public function testUppercaseKeywordIsNormalised(): void
    {
        // Detection is case-insensitive — uppercase keywords must match.
        $text = 'Maria Ionescu ESTE ACUZATĂ DE corupție în rețeaua regională.';

        $this->assertTrue($this->detector->isCategory6($text));
    }

    public function testKeywordsOutsideWindowDoNotCount(): void
    {
        // Two indicators far apart (over 50 words) should NOT trigger.
        $filler = str_repeat('un cuvânt ', 60);
        $text = 'Ion Popescu a deschis o cafenea. ' . $filler . 'O altă persoană este acuzată de ceva.';

        $this->assertFalse($this->detector->isCategory6($text));
    }
}
