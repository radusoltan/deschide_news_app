<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\SiteStatsDaily;
use DateTime;
use PHPUnit\Framework\TestCase;

class SiteStatsDailyTest extends TestCase
{
    private SiteStatsDaily $stats;

    protected function setUp(): void
    {
        $this->stats = new SiteStatsDaily();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->stats->getId());
        $this->assertSame(0, $this->stats->getTotalVisits());
        $this->assertSame(0, $this->stats->getUniqueVisitors());
        $this->assertSame(0, $this->stats->getNewVisitors());
        $this->assertNull($this->stats->getBounceRate());
        $this->assertNull($this->stats->getAvgSessionDuration());
    }

    public function testSetGetDate(): void
    {
        $date = new DateTime('2024-06-15');
        $result = $this->stats->setDate($date);
        $this->assertSame($date, $this->stats->getDate());
        $this->assertSame($this->stats, $result);
    }

    public function testSetGetTotalVisits(): void
    {
        $result = $this->stats->setTotalVisits(5000);
        $this->assertSame(5000, $this->stats->getTotalVisits());
        $this->assertSame($this->stats, $result);
    }

    public function testSetGetUniqueVisitors(): void
    {
        $result = $this->stats->setUniqueVisitors(3000);
        $this->assertSame(3000, $this->stats->getUniqueVisitors());
        $this->assertSame($this->stats, $result);
    }

    public function testSetGetNewVisitors(): void
    {
        $result = $this->stats->setNewVisitors(1500);
        $this->assertSame(1500, $this->stats->getNewVisitors());
        $this->assertSame($this->stats, $result);
    }

    public function testSetGetBounceRate(): void
    {
        $result = $this->stats->setBounceRate(45.50);
        $this->assertSame(45.50, $this->stats->getBounceRate());
        $this->assertSame($this->stats, $result);
    }

    public function testSetGetBounceRateNull(): void
    {
        $this->stats->setBounceRate(30.0);
        $this->stats->setBounceRate(null);
        $this->assertNull($this->stats->getBounceRate());
    }

    public function testBounceRateStoredAsStringConvertsToFloat(): void
    {
        $this->stats->setBounceRate(99.99);
        $this->assertIsFloat($this->stats->getBounceRate());
        $this->assertSame(99.99, $this->stats->getBounceRate());
    }

    public function testSetGetAvgSessionDuration(): void
    {
        $result = $this->stats->setAvgSessionDuration(300);
        $this->assertSame(300, $this->stats->getAvgSessionDuration());
        $this->assertSame($this->stats, $result);
    }

    public function testSetGetAvgSessionDurationNull(): void
    {
        $this->stats->setAvgSessionDuration(120);
        $this->stats->setAvgSessionDuration(null);
        $this->assertNull($this->stats->getAvgSessionDuration());
    }
}
