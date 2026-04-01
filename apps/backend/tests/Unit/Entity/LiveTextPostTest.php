<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\LiveText;
use App\Entity\LiveTextPost;
use App\Entity\User;
use DateTime;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;

class LiveTextPostTest extends TestCase
{
    private LiveTextPost $post;

    protected function setUp(): void
    {
        $this->post = new LiveTextPost();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->post->getId());
        $this->assertNull($this->post->getContent());
        $this->assertNull($this->post->getContentHtml());
        $this->assertFalse($this->post->isKeyPoint());
        $this->assertFalse($this->post->getIsKeyPoint());
        $this->assertSame(0, $this->post->getPosition());
        $this->assertNull($this->post->getLiveText());
        $this->assertNull($this->post->getAuthor());
        $this->assertNull($this->post->getCreatedAt());
        $this->assertNull($this->post->getUpdatedAt());
    }

    public function testConstructorSetsPublishedAt(): void
    {
        $this->assertInstanceOf(DateTimeInterface::class, $this->post->getPublishedAt());
    }

    public function testSetGetContent(): void
    {
        $result = $this->post->setContent('Breaking news update');
        $this->assertSame('Breaking news update', $this->post->getContent());
        $this->assertSame($this->post, $result);
    }

    public function testSetGetContentNull(): void
    {
        $this->post->setContent('test');
        $this->post->setContent(null);
        $this->assertNull($this->post->getContent());
    }

    public function testSetGetContentHtml(): void
    {
        $result = $this->post->setContentHtml('<p>Breaking news update</p>');
        $this->assertSame('<p>Breaking news update</p>', $this->post->getContentHtml());
        $this->assertSame($this->post, $result);
    }

    public function testSetGetContentHtmlNull(): void
    {
        $this->post->setContentHtml('<p>test</p>');
        $this->post->setContentHtml(null);
        $this->assertNull($this->post->getContentHtml());
    }

    public function testSetGetIsKeyPoint(): void
    {
        $result = $this->post->setIsKeyPoint(true);
        $this->assertTrue($this->post->isKeyPoint());
        $this->assertTrue($this->post->getIsKeyPoint());
        $this->assertSame($this->post, $result);
    }

    public function testSetGetPosition(): void
    {
        $result = $this->post->setPosition(5);
        $this->assertSame(5, $this->post->getPosition());
        $this->assertSame($this->post, $result);
    }

    public function testSetGetPublishedAt(): void
    {
        $date = new DateTime('2024-06-15 10:00:00');
        $result = $this->post->setPublishedAt($date);
        $this->assertSame($date, $this->post->getPublishedAt());
        $this->assertSame($this->post, $result);
    }

    public function testSetGetLiveText(): void
    {
        $liveText = new LiveText();
        $result = $this->post->setLiveText($liveText);
        $this->assertSame($liveText, $this->post->getLiveText());
        $this->assertSame($this->post, $result);
    }

    public function testSetGetLiveTextNull(): void
    {
        $liveText = new LiveText();
        $this->post->setLiveText($liveText);
        $this->post->setLiveText(null);
        $this->assertNull($this->post->getLiveText());
    }

    public function testSetGetAuthor(): void
    {
        $user = new User();
        $result = $this->post->setAuthor($user);
        $this->assertSame($user, $this->post->getAuthor());
        $this->assertSame($this->post, $result);
    }

    public function testSetGetAuthorNull(): void
    {
        $user = new User();
        $this->post->setAuthor($user);
        $this->post->setAuthor(null);
        $this->assertNull($this->post->getAuthor());
    }

    public function testSetGetCreatedAt(): void
    {
        $date = new DateTime('2024-06-15');
        $result = $this->post->setCreatedAt($date);
        $this->assertSame($date, $this->post->getCreatedAt());
        $this->assertSame($this->post, $result);
    }

    public function testSetGetUpdatedAt(): void
    {
        $date = new DateTime('2024-06-16');
        $result = $this->post->setUpdatedAt($date);
        $this->assertSame($date, $this->post->getUpdatedAt());
        $this->assertSame($this->post, $result);
    }
}
