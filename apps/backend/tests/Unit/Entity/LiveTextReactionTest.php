<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\LiveTextPost;
use App\Entity\LiveTextReaction;
use App\Entity\User;
use App\Enum\ReactionType;
use DateTime;
use PHPUnit\Framework\TestCase;

class LiveTextReactionTest extends TestCase
{
    private LiveTextReaction $reaction;

    protected function setUp(): void
    {
        $this->reaction = new LiveTextReaction();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->reaction->getId());
        $this->assertNull($this->reaction->getLiveTextPost());
        $this->assertNull($this->reaction->getUser());
        $this->assertNull($this->reaction->getIpAddress());
        $this->assertNull($this->reaction->getUserAgent());
        $this->assertNull($this->reaction->getCreatedAt());
    }

    public function testSetGetLiveTextPost(): void
    {
        $post = new LiveTextPost();
        $result = $this->reaction->setLiveTextPost($post);
        $this->assertSame($post, $this->reaction->getLiveTextPost());
        $this->assertSame($this->reaction, $result);
    }

    public function testSetGetLiveTextPostNull(): void
    {
        $post = new LiveTextPost();
        $this->reaction->setLiveTextPost($post);
        $this->reaction->setLiveTextPost(null);
        $this->assertNull($this->reaction->getLiveTextPost());
    }

    public function testSetGetUser(): void
    {
        $user = new User();
        $result = $this->reaction->setUser($user);
        $this->assertSame($user, $this->reaction->getUser());
        $this->assertSame($this->reaction, $result);
    }

    public function testSetGetUserNull(): void
    {
        $user = new User();
        $this->reaction->setUser($user);
        $this->reaction->setUser(null);
        $this->assertNull($this->reaction->getUser());
    }

    public function testSetGetReactionType(): void
    {
        $result = $this->reaction->setReactionType(ReactionType::LIKE);
        $this->assertSame(ReactionType::LIKE, $this->reaction->getReactionType());
        $this->assertSame($this->reaction, $result);
    }

    public function testSetGetReactionTypeAllValues(): void
    {
        foreach (ReactionType::cases() as $type) {
            $this->reaction->setReactionType($type);
            $this->assertSame($type, $this->reaction->getReactionType());
        }
    }

    public function testSetGetIpAddress(): void
    {
        $result = $this->reaction->setIpAddress('192.168.1.1');
        $this->assertSame('192.168.1.1', $this->reaction->getIpAddress());
        $this->assertSame($this->reaction, $result);
    }

    public function testSetGetIpAddressNull(): void
    {
        $this->reaction->setIpAddress('1.2.3.4');
        $this->reaction->setIpAddress(null);
        $this->assertNull($this->reaction->getIpAddress());
    }

    public function testSetGetUserAgent(): void
    {
        $result = $this->reaction->setUserAgent('Mozilla/5.0');
        $this->assertSame('Mozilla/5.0', $this->reaction->getUserAgent());
        $this->assertSame($this->reaction, $result);
    }

    public function testSetGetUserAgentNull(): void
    {
        $this->reaction->setUserAgent('test');
        $this->reaction->setUserAgent(null);
        $this->assertNull($this->reaction->getUserAgent());
    }

    public function testSetGetCreatedAt(): void
    {
        $date = new DateTime('2024-06-15');
        $result = $this->reaction->setCreatedAt($date);
        $this->assertSame($date, $this->reaction->getCreatedAt());
        $this->assertSame($this->reaction, $result);
    }
}
