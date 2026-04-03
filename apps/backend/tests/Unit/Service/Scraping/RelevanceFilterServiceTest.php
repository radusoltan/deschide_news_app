<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Scraping;

use App\Service\Scraping\RelevanceFilterService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class RelevanceFilterServiceTest extends TestCase
{
    private RelevanceFilterService $service;

    protected function setUp(): void
    {
        $this->service = new RelevanceFilterService(
            tier1Keywords: [
                'Moldova', 'Moldovan', 'Chișinău', 'Chisinau', 'Kishinev',
                'Кишинёв', 'Молдова', 'Transnistria', 'Transdniestria',
                'Приднестровье', 'Gagauzia', 'Гагаузия', 'Sandu', 'Recean', 'Moldpres',
            ],
            tier2Keywords: [
                'Eastern Europe', 'Black Sea', 'EU enlargement', 'EU accession',
                'Eastern Partnership', 'Russia sanctions', 'energy security Europe',
                'NATO east', 'Ukraine border', 'Romania Moldova', 'OSCE',
                'IMF Eastern Europe', 'World Bank Moldova',
            ],
            tier3Keywords: [
                'Maia Sandu', 'Dorin Recean', 'Igor Dodon', 'Ilan Shor',
                'Krasnoselsky', 'Красносельский', 'Moldovagaz', 'Energocom',
            ],
            minScore: 1,
            logger: new NullLogger(),
        );
    }

    #[Test]
    public function tier1MoldovaDirectMentionIsRelevant(): void
    {
        $result = $this->service->evaluate(
            'EU signs new cooperation agreement with Moldova',
            'The European Union announced a new cooperation framework with the Republic of Moldova today.',
            'Reuters',
        );

        $this->assertTrue($result->isRelevant);
        $this->assertGreaterThanOrEqual(3, $result->score);
        $this->assertGreaterThanOrEqual(1, $result->tier1Count);
    }

    #[Test]
    public function tier1ChisinauMentionIsRelevant(): void
    {
        $result = $this->service->evaluate(
            'Protests erupt in Chișinău over energy prices',
            'Thousands gathered in the capital Chișinău demanding lower gas bills.',
            'AP News',
        );

        $this->assertTrue($result->isRelevant);
        $this->assertGreaterThanOrEqual(3, $result->score);
    }

    #[Test]
    public function tier1SanduMentionIsRelevant(): void
    {
        $result = $this->service->evaluate(
            'President Sandu addresses the parliament',
            'In a historic speech, Sandu laid out plans for the country.',
            'Agerpres',
        );

        $this->assertTrue($result->isRelevant);
        $this->assertGreaterThanOrEqual(3, $result->score);
    }

    #[Test]
    public function tier2SingleKeywordIsIrrelevant(): void
    {
        $result = $this->service->evaluate(
            'NATO expansion discussed at summit',
            'Leaders from across the alliance met to discuss NATO east expansion.',
            'Reuters',
        );

        // Only 1 tier2 match — below threshold of 2
        $this->assertFalse($result->isRelevant);
        $this->assertSame(0, $result->score);
    }

    #[Test]
    public function tier2TwoOrMoreKeywordsIsRelevant(): void
    {
        $result = $this->service->evaluate(
            'EU enlargement and Eastern Partnership: a review',
            'The EU enlargement process and the Eastern Partnership initiative were discussed at the summit. Black Sea region security was on the agenda.',
            'Reuters',
        );

        $this->assertTrue($result->isRelevant);
        $this->assertGreaterThanOrEqual(2, $result->score);
        $this->assertGreaterThanOrEqual(2, $result->tier2Count);
    }

    #[Test]
    public function tier3MaiaSanduEntityIsRelevant(): void
    {
        $result = $this->service->evaluate(
            'Interview with Maia Sandu on European integration',
            'In an exclusive interview, Maia Sandu discussed the future path toward EU membership.',
            'Reuters',
        );

        $this->assertTrue($result->isRelevant);
        // Maia Sandu = tier3 (score 2) + "Sandu" = tier1 (score 3) = 5
        $this->assertGreaterThanOrEqual(2, $result->score);
    }

    #[Test]
    public function completelyIrrelevantArticleReturnsZeroScore(): void
    {
        $result = $this->service->evaluate(
            'Heavy snowfall expected in Kansas this weekend',
            'The National Weather Service issued a winter storm warning for Kansas and Nebraska. Residents are advised to stock up on supplies.',
            'AP News',
        );

        $this->assertFalse($result->isRelevant);
        $this->assertSame(0, $result->score);
        $this->assertSame([], $result->matches);
        $this->assertSame(0, $result->tier1Count);
        $this->assertSame(0, $result->tier2Count);
        $this->assertSame(0, $result->tier3Count);
    }

    #[Test]
    public function cyrillicKeywordsAreDetected(): void
    {
        $result = $this->service->evaluate(
            'Новости из Молдова',
            'Правительство Молдова приняло новый закон о реформах. Красносельский прокомментировал решение.',
            'UNIAN',
        );

        $this->assertTrue($result->isRelevant);
        // Молдова (tier1, score 3) + Красносельский (tier3, score 2)
        $this->assertGreaterThanOrEqual(5, $result->score);
        $this->assertGreaterThanOrEqual(1, $result->tier1Count);
        $this->assertGreaterThanOrEqual(1, $result->tier3Count);
    }
}
