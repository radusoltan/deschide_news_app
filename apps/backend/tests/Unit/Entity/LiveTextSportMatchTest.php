<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\LiveText;
use App\Entity\LiveTextMatchEvent;
use App\Entity\LiveTextSportMatch;
use DateTime;
use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use PHPUnit\Framework\TestCase;

class LiveTextSportMatchTest extends TestCase
{
    private LiveTextSportMatch $match;

    protected function setUp(): void
    {
        $this->match = new LiveTextSportMatch();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->match->getId());
        $this->assertNull($this->match->getLiveText());
        $this->assertNull($this->match->getSportType());
        $this->assertNull($this->match->getHomeTeam());
        $this->assertNull($this->match->getAwayTeam());
        $this->assertNull($this->match->getHomeTeamLogo());
        $this->assertNull($this->match->getAwayTeamLogo());
        $this->assertSame(0, $this->match->getHomeScore());
        $this->assertSame(0, $this->match->getAwayScore());
        $this->assertSame('not_started', $this->match->getStatus());
        $this->assertNull($this->match->getCurrentMinute());
        $this->assertNull($this->match->getCurrentPeriod());
        $this->assertNull($this->match->getVenue());
        $this->assertNull($this->match->getCompetition());
        $this->assertNull($this->match->getScheduledStartTime());
        $this->assertNull($this->match->getActualStartTime());
        $this->assertNull($this->match->getEndTime());
        $this->assertNull($this->match->getStatistics());
        $this->assertInstanceOf(Collection::class, $this->match->getEvents());
        $this->assertCount(0, $this->match->getEvents());
    }

    public function testConstructorSetsTimestamps(): void
    {
        $this->assertInstanceOf(DateTimeInterface::class, $this->match->getCreatedAt());
        $this->assertInstanceOf(DateTimeInterface::class, $this->match->getUpdatedAt());
    }

    public function testSetGetLiveText(): void
    {
        $liveText = new LiveText();
        $result = $this->match->setLiveText($liveText);
        $this->assertSame($liveText, $this->match->getLiveText());
        $this->assertSame($this->match, $result);
    }

    public function testSetGetSportType(): void
    {
        $result = $this->match->setSportType('football');
        $this->assertSame('football', $this->match->getSportType());
        $this->assertSame($this->match, $result);
    }

    public function testSetGetHomeTeam(): void
    {
        $result = $this->match->setHomeTeam('FC Barcelona');
        $this->assertSame('FC Barcelona', $this->match->getHomeTeam());
        $this->assertSame($this->match, $result);
    }

    public function testSetGetAwayTeam(): void
    {
        $result = $this->match->setAwayTeam('Real Madrid');
        $this->assertSame('Real Madrid', $this->match->getAwayTeam());
        $this->assertSame($this->match, $result);
    }

    public function testSetGetHomeTeamLogo(): void
    {
        $result = $this->match->setHomeTeamLogo('https://example.com/logo.png');
        $this->assertSame('https://example.com/logo.png', $this->match->getHomeTeamLogo());
        $this->assertSame($this->match, $result);
    }

    public function testSetGetAwayTeamLogo(): void
    {
        $result = $this->match->setAwayTeamLogo('https://example.com/away.png');
        $this->assertSame('https://example.com/away.png', $this->match->getAwayTeamLogo());
        $this->assertSame($this->match, $result);
    }

    public function testSetGetHomeScore(): void
    {
        $result = $this->match->setHomeScore(3);
        $this->assertSame(3, $this->match->getHomeScore());
        $this->assertSame($this->match, $result);
    }

    public function testSetHomeScoreUpdatesTimestamp(): void
    {
        $oldUpdated = $this->match->getUpdatedAt();
        usleep(1000); // Ensure different timestamp
        $this->match->setHomeScore(1);
        $this->assertGreaterThanOrEqual($oldUpdated, $this->match->getUpdatedAt());
    }

    public function testSetGetAwayScore(): void
    {
        $result = $this->match->setAwayScore(2);
        $this->assertSame(2, $this->match->getAwayScore());
        $this->assertSame($this->match, $result);
    }

    public function testSetGetStatus(): void
    {
        $result = $this->match->setStatus('live');
        $this->assertSame('live', $this->match->getStatus());
        $this->assertSame($this->match, $result);
    }

    public function testSetGetCurrentMinute(): void
    {
        $result = $this->match->setCurrentMinute(45);
        $this->assertSame(45, $this->match->getCurrentMinute());
        $this->assertSame($this->match, $result);
    }

    public function testSetGetCurrentPeriod(): void
    {
        $result = $this->match->setCurrentPeriod('Q2');
        $this->assertSame('Q2', $this->match->getCurrentPeriod());
        $this->assertSame($this->match, $result);
    }

    public function testSetGetVenue(): void
    {
        $result = $this->match->setVenue('Camp Nou');
        $this->assertSame('Camp Nou', $this->match->getVenue());
        $this->assertSame($this->match, $result);
    }

    public function testSetGetCompetition(): void
    {
        $result = $this->match->setCompetition('La Liga');
        $this->assertSame('La Liga', $this->match->getCompetition());
        $this->assertSame($this->match, $result);
    }

    public function testSetGetScheduledStartTime(): void
    {
        $date = new DateTime('2024-06-15 20:00:00');
        $result = $this->match->setScheduledStartTime($date);
        $this->assertSame($date, $this->match->getScheduledStartTime());
        $this->assertSame($this->match, $result);
    }

    public function testSetGetActualStartTime(): void
    {
        $date = new DateTime('2024-06-15 20:05:00');
        $result = $this->match->setActualStartTime($date);
        $this->assertSame($date, $this->match->getActualStartTime());
        $this->assertSame($this->match, $result);
    }

    public function testSetGetEndTime(): void
    {
        $date = new DateTime('2024-06-15 22:00:00');
        $result = $this->match->setEndTime($date);
        $this->assertSame($date, $this->match->getEndTime());
        $this->assertSame($this->match, $result);
    }

    public function testSetGetStatistics(): void
    {
        $stats = ['possession' => ['home' => 55, 'away' => 45], 'shots' => 20];
        $result = $this->match->setStatistics($stats);
        $this->assertSame($stats, $this->match->getStatistics());
        $this->assertSame($this->match, $result);
    }

    public function testSetGetStatisticsNull(): void
    {
        $this->match->setStatistics(['test' => 1]);
        $this->match->setStatistics(null);
        $this->assertNull($this->match->getStatistics());
    }

    public function testAddEvent(): void
    {
        $event = new LiveTextMatchEvent();
        $result = $this->match->addEvent($event);
        $this->assertCount(1, $this->match->getEvents());
        $this->assertTrue($this->match->getEvents()->contains($event));
        $this->assertSame($this->match, $event->getSportMatch());
        $this->assertSame($this->match, $result);
    }

    public function testAddEventDoesNotDuplicate(): void
    {
        $event = new LiveTextMatchEvent();
        $this->match->addEvent($event);
        $this->match->addEvent($event);
        $this->assertCount(1, $this->match->getEvents());
    }

    public function testRemoveEvent(): void
    {
        $event = new LiveTextMatchEvent();
        $this->match->addEvent($event);
        $result = $this->match->removeEvent($event);
        $this->assertCount(0, $this->match->getEvents());
        $this->assertNull($event->getSportMatch());
        $this->assertSame($this->match, $result);
    }

    public function testSetGetCreatedAt(): void
    {
        $date = new DateTime('2024-01-01');
        $result = $this->match->setCreatedAt($date);
        $this->assertSame($date, $this->match->getCreatedAt());
        $this->assertSame($this->match, $result);
    }

    public function testSetGetUpdatedAt(): void
    {
        $date = new DateTime('2024-01-02');
        $result = $this->match->setUpdatedAt($date);
        $this->assertSame($date, $this->match->getUpdatedAt());
        $this->assertSame($this->match, $result);
    }
}
