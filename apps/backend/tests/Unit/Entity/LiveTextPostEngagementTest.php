<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\LiveTextPost;
use App\Entity\LiveTextPostEngagement;
use App\Entity\User;
use DateTime;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;

class LiveTextPostEngagementTest extends TestCase
{
    private LiveTextPostEngagement $engagement;

    protected function setUp(): void
    {
        $this->engagement = new LiveTextPostEngagement();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->engagement->getId());
        $this->assertNull($this->engagement->getPost());
        $this->assertNull($this->engagement->getSessionId());
        $this->assertNull($this->engagement->getUser());
        $this->assertNull($this->engagement->getEngagementType());
        $this->assertNull($this->engagement->getTimeSpent());
        $this->assertNull($this->engagement->getScrollDepth());
        $this->assertNull($this->engagement->getClickedElement());
        $this->assertNull($this->engagement->getMetadata());
        $this->assertNull($this->engagement->getIpAddress());
        $this->assertNull($this->engagement->getUserAgent());
    }

    public function testConstructorSetsCreatedAt(): void
    {
        $this->assertInstanceOf(DateTimeInterface::class, $this->engagement->getCreatedAt());
    }

    public function testSetGetPost(): void
    {
        $post = new LiveTextPost();
        $result = $this->engagement->setPost($post);
        $this->assertSame($post, $this->engagement->getPost());
        $this->assertSame($this->engagement, $result);
    }

    public function testSetGetPostNull(): void
    {
        $post = new LiveTextPost();
        $this->engagement->setPost($post);
        $this->engagement->setPost(null);
        $this->assertNull($this->engagement->getPost());
    }

    public function testSetGetSessionId(): void
    {
        $result = $this->engagement->setSessionId('session_xyz');
        $this->assertSame('session_xyz', $this->engagement->getSessionId());
        $this->assertSame($this->engagement, $result);
    }

    public function testSetGetUser(): void
    {
        $user = new User();
        $result = $this->engagement->setUser($user);
        $this->assertSame($user, $this->engagement->getUser());
        $this->assertSame($this->engagement, $result);
    }

    public function testSetGetUserNull(): void
    {
        $user = new User();
        $this->engagement->setUser($user);
        $this->engagement->setUser(null);
        $this->assertNull($this->engagement->getUser());
    }

    public function testSetGetEngagementType(): void
    {
        $result = $this->engagement->setEngagementType('view');
        $this->assertSame('view', $this->engagement->getEngagementType());
        $this->assertSame($this->engagement, $result);
    }

    public function testSetGetTimeSpent(): void
    {
        $result = $this->engagement->setTimeSpent(120);
        $this->assertSame(120, $this->engagement->getTimeSpent());
        $this->assertSame($this->engagement, $result);
    }

    public function testSetGetTimeSpentNull(): void
    {
        $this->engagement->setTimeSpent(60);
        $this->engagement->setTimeSpent(null);
        $this->assertNull($this->engagement->getTimeSpent());
    }

    public function testSetGetScrollDepth(): void
    {
        $result = $this->engagement->setScrollDepth(80);
        $this->assertSame(80, $this->engagement->getScrollDepth());
        $this->assertSame($this->engagement, $result);
    }

    public function testSetGetScrollDepthNull(): void
    {
        $this->engagement->setScrollDepth(50);
        $this->engagement->setScrollDepth(null);
        $this->assertNull($this->engagement->getScrollDepth());
    }

    public function testSetGetClickedElement(): void
    {
        $result = $this->engagement->setClickedElement('image');
        $this->assertSame('image', $this->engagement->getClickedElement());
        $this->assertSame($this->engagement, $result);
    }

    public function testSetGetMetadata(): void
    {
        $metadata = ['browser' => 'Chrome', 'resolution' => '1920x1080'];
        $result = $this->engagement->setMetadata($metadata);
        $this->assertSame($metadata, $this->engagement->getMetadata());
        $this->assertSame($this->engagement, $result);
    }

    public function testSetGetMetadataNull(): void
    {
        $this->engagement->setMetadata(['test' => 1]);
        $this->engagement->setMetadata(null);
        $this->assertNull($this->engagement->getMetadata());
    }

    public function testSetGetIpAddress(): void
    {
        $result = $this->engagement->setIpAddress('10.0.0.1');
        $this->assertSame('10.0.0.1', $this->engagement->getIpAddress());
        $this->assertSame($this->engagement, $result);
    }

    public function testSetGetUserAgent(): void
    {
        $result = $this->engagement->setUserAgent('Mozilla/5.0');
        $this->assertSame('Mozilla/5.0', $this->engagement->getUserAgent());
        $this->assertSame($this->engagement, $result);
    }

    public function testSetGetCreatedAt(): void
    {
        $date = new DateTime('2024-06-15');
        $result = $this->engagement->setCreatedAt($date);
        $this->assertSame($date, $this->engagement->getCreatedAt());
        $this->assertSame($this->engagement, $result);
    }
}
