<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\LiveTextMatchEvent;
use App\Entity\LiveTextSportMatch;
use DateTime;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;

class LiveTextMatchEventTest extends TestCase
{
    private LiveTextMatchEvent $event;

    protected function setUp(): void
    {
        $this->event = new LiveTextMatchEvent();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->event->getId());
        $this->assertNull($this->event->getSportMatch());
        $this->assertNull($this->event->getEventType());
        $this->assertNull($this->event->getTeam());
        $this->assertNull($this->event->getPlayerName());
        $this->assertNull($this->event->getSecondPlayerName());
        $this->assertNull($this->event->getEventMinute());
        $this->assertNull($this->event->getExtraTimeMinute());
        $this->assertNull($this->event->getScoreAfterEvent());
        $this->assertNull($this->event->getDescription());
        $this->assertNull($this->event->getMetadata());
    }

    public function testConstructorSetsCreatedAt(): void
    {
        $this->assertInstanceOf(DateTimeInterface::class, $this->event->getCreatedAt());
    }

    public function testSetGetSportMatch(): void
    {
        $match = new LiveTextSportMatch();
        $result = $this->event->setSportMatch($match);
        $this->assertSame($match, $this->event->getSportMatch());
        $this->assertSame($this->event, $result);
    }

    public function testSetGetSportMatchNull(): void
    {
        $match = new LiveTextSportMatch();
        $this->event->setSportMatch($match);
        $this->event->setSportMatch(null);
        $this->assertNull($this->event->getSportMatch());
    }

    public function testSetGetEventType(): void
    {
        $result = $this->event->setEventType('goal');
        $this->assertSame('goal', $this->event->getEventType());
        $this->assertSame($this->event, $result);
    }

    public function testSetGetTeam(): void
    {
        $result = $this->event->setTeam('home');
        $this->assertSame('home', $this->event->getTeam());
        $this->assertSame($this->event, $result);
    }

    public function testSetGetPlayerName(): void
    {
        $result = $this->event->setPlayerName('Messi');
        $this->assertSame('Messi', $this->event->getPlayerName());
        $this->assertSame($this->event, $result);
    }

    public function testSetGetSecondPlayerName(): void
    {
        $result = $this->event->setSecondPlayerName('Ronaldo');
        $this->assertSame('Ronaldo', $this->event->getSecondPlayerName());
        $this->assertSame($this->event, $result);
    }

    public function testSetGetEventMinute(): void
    {
        $result = $this->event->setEventMinute(45);
        $this->assertSame(45, $this->event->getEventMinute());
        $this->assertSame($this->event, $result);
    }

    public function testSetGetExtraTimeMinute(): void
    {
        $result = $this->event->setExtraTimeMinute(3);
        $this->assertSame(3, $this->event->getExtraTimeMinute());
        $this->assertSame($this->event, $result);
    }

    public function testSetGetScoreAfterEvent(): void
    {
        $result = $this->event->setScoreAfterEvent('2-1');
        $this->assertSame('2-1', $this->event->getScoreAfterEvent());
        $this->assertSame($this->event, $result);
    }

    public function testSetGetDescription(): void
    {
        $result = $this->event->setDescription('Amazing goal from outside the box');
        $this->assertSame('Amazing goal from outside the box', $this->event->getDescription());
        $this->assertSame($this->event, $result);
    }

    public function testSetGetMetadata(): void
    {
        $metadata = ['assist' => 'Iniesta', 'video_url' => 'https://example.com/goal.mp4'];
        $result = $this->event->setMetadata($metadata);
        $this->assertSame($metadata, $this->event->getMetadata());
        $this->assertSame($this->event, $result);
    }

    public function testSetGetMetadataNull(): void
    {
        $this->event->setMetadata(['test' => 1]);
        $this->event->setMetadata(null);
        $this->assertNull($this->event->getMetadata());
    }

    public function testSetGetCreatedAt(): void
    {
        $date = new DateTime('2024-06-15');
        $result = $this->event->setCreatedAt($date);
        $this->assertSame($date, $this->event->getCreatedAt());
        $this->assertSame($this->event, $result);
    }

    public function testGetFormattedMinuteWithoutExtraTime(): void
    {
        $this->event->setEventMinute(30);
        $this->assertSame('30', $this->event->getFormattedMinute());
    }

    public function testGetFormattedMinuteWithExtraTime(): void
    {
        $this->event->setEventMinute(45);
        $this->event->setExtraTimeMinute(2);
        $this->assertSame('45+2', $this->event->getFormattedMinute());
    }

    public function testGetFormattedMinuteWithZeroExtraTime(): void
    {
        $this->event->setEventMinute(90);
        $this->event->setExtraTimeMinute(0);
        $this->assertSame('90', $this->event->getFormattedMinute());
    }

    public function testGetEventIconGoal(): void
    {
        $this->event->setEventType('goal');
        $this->assertSame('goal', $this->event->getEventIcon());
    }

    public function testGetEventIconPenaltyGoal(): void
    {
        $this->event->setEventType('penalty_goal');
        $this->assertSame('goal', $this->event->getEventIcon());
    }

    public function testGetEventIconOwnGoal(): void
    {
        $this->event->setEventType('own_goal');
        $this->assertSame('own-goal', $this->event->getEventIcon());
    }

    public function testGetEventIconMissedPenalty(): void
    {
        $this->event->setEventType('missed_penalty');
        $this->assertSame('missed-penalty', $this->event->getEventIcon());
    }

    public function testGetEventIconYellowCard(): void
    {
        $this->event->setEventType('yellow_card');
        $this->assertSame('yellow-card', $this->event->getEventIcon());
    }

    public function testGetEventIconRedCard(): void
    {
        $this->event->setEventType('red_card');
        $this->assertSame('red-card', $this->event->getEventIcon());
    }

    public function testGetEventIconSecondYellowCard(): void
    {
        $this->event->setEventType('second_yellow_card');
        $this->assertSame('red-card', $this->event->getEventIcon());
    }

    public function testGetEventIconSubstitution(): void
    {
        $this->event->setEventType('substitution');
        $this->assertSame('substitution', $this->event->getEventIcon());
    }

    public function testGetEventIconVar(): void
    {
        $this->event->setEventType('var_check');
        $this->assertSame('var', $this->event->getEventIcon());
    }

    public function testGetEventIconInjury(): void
    {
        $this->event->setEventType('injury');
        $this->assertSame('injury', $this->event->getEventIcon());
    }

    public function testGetEventIconDefault(): void
    {
        $this->event->setEventType('corner');
        $this->assertSame('event', $this->event->getEventIcon());
    }
}
