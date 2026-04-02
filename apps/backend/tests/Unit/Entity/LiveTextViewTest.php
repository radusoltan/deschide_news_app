<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\LiveText;
use App\Entity\LiveTextView;
use App\Entity\User;
use DateTime;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;

class LiveTextViewTest extends TestCase
{
    private LiveTextView $view;

    protected function setUp(): void
    {
        $this->view = new LiveTextView();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->view->getId());
        $this->assertNull($this->view->getLiveText());
        $this->assertNull($this->view->getUser());
        $this->assertNull($this->view->getSessionId());
        $this->assertSame(0, $this->view->getTimeSpent());
        $this->assertNull($this->view->getIpAddress());
        $this->assertNull($this->view->getUserAgent());
    }

    public function testConstructorSetsViewedAt(): void
    {
        $this->assertInstanceOf(DateTimeInterface::class, $this->view->getViewedAt());
    }

    public function testConstructorSetsLastActivityAt(): void
    {
        $this->assertInstanceOf(DateTimeInterface::class, $this->view->getLastActivityAt());
    }

    public function testSetGetLiveText(): void
    {
        $liveText = new LiveText();
        $result = $this->view->setLiveText($liveText);
        $this->assertSame($liveText, $this->view->getLiveText());
        $this->assertSame($this->view, $result);
    }

    public function testSetGetLiveTextNull(): void
    {
        $liveText = new LiveText();
        $this->view->setLiveText($liveText);
        $this->view->setLiveText(null);
        $this->assertNull($this->view->getLiveText());
    }

    public function testSetGetUser(): void
    {
        $user = new User();
        $result = $this->view->setUser($user);
        $this->assertSame($user, $this->view->getUser());
        $this->assertSame($this->view, $result);
    }

    public function testSetGetUserNull(): void
    {
        $user = new User();
        $this->view->setUser($user);
        $this->view->setUser(null);
        $this->assertNull($this->view->getUser());
    }

    public function testSetGetSessionId(): void
    {
        $result = $this->view->setSessionId('session_abc');
        $this->assertSame('session_abc', $this->view->getSessionId());
        $this->assertSame($this->view, $result);
    }

    public function testSetGetTimeSpent(): void
    {
        $result = $this->view->setTimeSpent(300);
        $this->assertSame(300, $this->view->getTimeSpent());
        $this->assertSame($this->view, $result);
    }

    public function testAddTimeSpent(): void
    {
        $this->view->setTimeSpent(100);
        $result = $this->view->addTimeSpent(50);
        $this->assertSame(150, $this->view->getTimeSpent());
        $this->assertSame($this->view, $result);
    }

    public function testAddTimeSpentFromZero(): void
    {
        $this->view->addTimeSpent(30);
        $this->assertSame(30, $this->view->getTimeSpent());
    }

    public function testAddTimeSpentMultiple(): void
    {
        $this->view->addTimeSpent(10);
        $this->view->addTimeSpent(20);
        $this->view->addTimeSpent(30);
        $this->assertSame(60, $this->view->getTimeSpent());
    }

    public function testSetGetIpAddress(): void
    {
        $result = $this->view->setIpAddress('192.168.1.1');
        $this->assertSame('192.168.1.1', $this->view->getIpAddress());
        $this->assertSame($this->view, $result);
    }

    public function testSetGetIpAddressNull(): void
    {
        $this->view->setIpAddress('1.2.3.4');
        $this->view->setIpAddress(null);
        $this->assertNull($this->view->getIpAddress());
    }

    public function testSetGetUserAgent(): void
    {
        $result = $this->view->setUserAgent('Mozilla/5.0');
        $this->assertSame('Mozilla/5.0', $this->view->getUserAgent());
        $this->assertSame($this->view, $result);
    }

    public function testSetGetUserAgentNull(): void
    {
        $this->view->setUserAgent('test');
        $this->view->setUserAgent(null);
        $this->assertNull($this->view->getUserAgent());
    }

    public function testSetGetViewedAt(): void
    {
        $date = new DateTime('2024-06-15 10:00:00');
        $result = $this->view->setViewedAt($date);
        $this->assertSame($date, $this->view->getViewedAt());
        $this->assertSame($this->view, $result);
    }

    public function testSetGetLastActivityAt(): void
    {
        $date = new DateTime('2024-06-15 10:30:00');
        $result = $this->view->setLastActivityAt($date);
        $this->assertSame($date, $this->view->getLastActivityAt());
        $this->assertSame($this->view, $result);
    }

    public function testUpdateActivity(): void
    {
        $oldActivity = $this->view->getLastActivityAt();
        usleep(1000);
        $result = $this->view->updateActivity();
        $newActivity = $this->view->getLastActivityAt();
        $this->assertGreaterThanOrEqual($oldActivity, $newActivity);
        $this->assertSame($this->view, $result);
    }
}
