<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Article;
use App\Entity\ArticleStatsDaily;
use DateTime;
use PHPUnit\Framework\TestCase;

class ArticleStatsDailyTest extends TestCase
{
    private ArticleStatsDaily $stats;

    protected function setUp(): void
    {
        $this->stats = new ArticleStatsDaily();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->stats->getId());
        $this->assertSame(0, $this->stats->getViews());
        $this->assertSame(0, $this->stats->getUniqueVisitors());
        $this->assertNull($this->stats->getAvgReadingTime());
        $this->assertNull($this->stats->getCompletionRate());
    }

    public function testSetGetArticle(): void
    {
        $article = $this->createStub(Article::class);
        $result = $this->stats->setArticle($article);
        $this->assertSame($article, $this->stats->getArticle());
        $this->assertSame($this->stats, $result);
    }

    public function testSetGetDate(): void
    {
        $date = new DateTime('2024-06-15');
        $result = $this->stats->setDate($date);
        $this->assertSame($date, $this->stats->getDate());
        $this->assertSame($this->stats, $result);
    }

    public function testSetGetViews(): void
    {
        $result = $this->stats->setViews(1500);
        $this->assertSame(1500, $this->stats->getViews());
        $this->assertSame($this->stats, $result);
    }

    public function testSetGetUniqueVisitors(): void
    {
        $result = $this->stats->setUniqueVisitors(800);
        $this->assertSame(800, $this->stats->getUniqueVisitors());
        $this->assertSame($this->stats, $result);
    }

    public function testSetGetAvgReadingTime(): void
    {
        $result = $this->stats->setAvgReadingTime(180);
        $this->assertSame(180, $this->stats->getAvgReadingTime());
        $this->assertSame($this->stats, $result);
    }

    public function testSetGetAvgReadingTimeNull(): void
    {
        $this->stats->setAvgReadingTime(60);
        $this->stats->setAvgReadingTime(null);
        $this->assertNull($this->stats->getAvgReadingTime());
    }

    public function testSetGetCompletionRate(): void
    {
        $result = $this->stats->setCompletionRate(75.50);
        $this->assertSame(75.50, $this->stats->getCompletionRate());
        $this->assertSame($this->stats, $result);
    }

    public function testSetGetCompletionRateNull(): void
    {
        $this->stats->setCompletionRate(50.0);
        $this->stats->setCompletionRate(null);
        $this->assertNull($this->stats->getCompletionRate());
    }

    public function testCompletionRateStoredAsStringConvertsToFloat(): void
    {
        $this->stats->setCompletionRate(99.99);
        $this->assertIsFloat($this->stats->getCompletionRate());
        $this->assertSame(99.99, $this->stats->getCompletionRate());
    }
}
