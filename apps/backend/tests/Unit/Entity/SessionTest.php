<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Session;
use DateTime;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;

class SessionTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        $this->session = new Session();
    }

    public function testConstructorSetsId(): void
    {
        $id = $this->session->getId();
        $this->assertNotEmpty($id);
        $this->assertIsString($id);
        // UUID v4 format
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $id
        );
    }

    public function testConstructorSetsStartedAt(): void
    {
        $this->assertInstanceOf(DateTimeInterface::class, $this->session->getStartedAt());
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->session->getIpAddress());
        $this->assertNull($this->session->getUserAgent());
        $this->assertNull($this->session->getReferrer());
        $this->assertNull($this->session->getEndedAt());
        $this->assertSame(0, $this->session->getPageCount());
        $this->assertNull($this->session->getDuration());
    }

    public function testSetGetId(): void
    {
        $result = $this->session->setId('custom-uuid');
        $this->assertSame('custom-uuid', $this->session->getId());
        $this->assertSame($this->session, $result);
    }

    public function testSetGetVisitorId(): void
    {
        $result = $this->session->setVisitorId('visitor_abc');
        $this->assertSame('visitor_abc', $this->session->getVisitorId());
        $this->assertSame($this->session, $result);
    }

    public function testSetGetIpAddress(): void
    {
        $result = $this->session->setIpAddress('192.168.1.1');
        $this->assertSame('192.168.1.1', $this->session->getIpAddress());
        $this->assertSame($this->session, $result);
    }

    public function testSetGetIpAddressNull(): void
    {
        $this->session->setIpAddress('1.2.3.4');
        $this->session->setIpAddress(null);
        $this->assertNull($this->session->getIpAddress());
    }

    public function testSetGetUserAgent(): void
    {
        $result = $this->session->setUserAgent('Mozilla/5.0');
        $this->assertSame('Mozilla/5.0', $this->session->getUserAgent());
        $this->assertSame($this->session, $result);
    }

    public function testSetGetReferrer(): void
    {
        $result = $this->session->setReferrer('https://google.com');
        $this->assertSame('https://google.com', $this->session->getReferrer());
        $this->assertSame($this->session, $result);
    }

    public function testSetGetStartedAt(): void
    {
        $date = new DateTime('2024-06-15 10:00:00');
        $result = $this->session->setStartedAt($date);
        $this->assertSame($date, $this->session->getStartedAt());
        $this->assertSame($this->session, $result);
    }

    public function testSetGetEndedAt(): void
    {
        $date = new DateTime('2024-06-15 10:30:00');
        $result = $this->session->setEndedAt($date);
        $this->assertSame($date, $this->session->getEndedAt());
        $this->assertSame($this->session, $result);
    }

    public function testSetEndedAtCalculatesDuration(): void
    {
        $start = new DateTime('2024-06-15 10:00:00');
        $end = new DateTime('2024-06-15 10:05:00');
        $this->session->setStartedAt($start);
        $this->session->setEndedAt($end);
        $this->assertSame(300, $this->session->getDuration());
    }

    public function testSetEndedAtNullDoesNotCalculateDuration(): void
    {
        $this->session->setEndedAt(null);
        $this->assertNull($this->session->getDuration());
    }

    public function testSetGetPageCount(): void
    {
        $result = $this->session->setPageCount(5);
        $this->assertSame(5, $this->session->getPageCount());
        $this->assertSame($this->session, $result);
    }

    public function testIncrementPageCount(): void
    {
        $this->session->setPageCount(3);
        $result = $this->session->incrementPageCount();
        $this->assertSame(4, $this->session->getPageCount());
        $this->assertSame($this->session, $result);
    }

    public function testIncrementPageCountFromZero(): void
    {
        $this->session->incrementPageCount();
        $this->assertSame(1, $this->session->getPageCount());
    }

    public function testSetGetDuration(): void
    {
        $result = $this->session->setDuration(600);
        $this->assertSame(600, $this->session->getDuration());
        $this->assertSame($this->session, $result);
    }

    public function testSetGetDurationNull(): void
    {
        $this->session->setDuration(100);
        $this->session->setDuration(null);
        $this->assertNull($this->session->getDuration());
    }

    public function testUniqueIds(): void
    {
        $session2 = new Session();
        $this->assertNotSame($this->session->getId(), $session2->getId());
    }
}
