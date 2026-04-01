<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Article;
use App\Entity\ArticleLock;
use App\Entity\User;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ArticleLockTest extends TestCase
{
    private ArticleLock $lock;

    protected function setUp(): void
    {
        $this->lock = new ArticleLock();
    }

    public function testDefaultValues(): void
    {
        $this->assertNull($this->lock->getId());
        $this->assertNull($this->lock->getArticle());
        $this->assertNull($this->lock->getLockedBy());
        $this->assertNull($this->lock->getSessionId());
    }

    public function testConstructorSetsLockedAt(): void
    {
        $lockedAt = $this->lock->getLockedAt();
        $this->assertInstanceOf(DateTimeImmutable::class, $lockedAt);
        // Should be very recent
        $diff = (new DateTimeImmutable())->getTimestamp() - $lockedAt->getTimestamp();
        $this->assertLessThanOrEqual(1, $diff);
    }

    public function testConstructorSetsExpiresAt(): void
    {
        $expiresAt = $this->lock->getExpiresAt();
        $this->assertInstanceOf(DateTimeImmutable::class, $expiresAt);
        // Should be approximately 15 minutes from now
        $diff = $expiresAt->getTimestamp() - (new DateTimeImmutable())->getTimestamp();
        $this->assertGreaterThan(14 * 60, $diff);
        $this->assertLessThanOrEqual(15 * 60 + 1, $diff);
    }

    public function testSetGetArticle(): void
    {
        $article = $this->createStub(Article::class);
        $result = $this->lock->setArticle($article);
        $this->assertSame($article, $this->lock->getArticle());
        $this->assertSame($this->lock, $result);
    }

    public function testSetGetArticleNull(): void
    {
        $article = $this->createStub(Article::class);
        $this->lock->setArticle($article);
        $this->lock->setArticle(null);
        $this->assertNull($this->lock->getArticle());
    }

    public function testSetGetLockedBy(): void
    {
        $user = new User();
        $result = $this->lock->setLockedBy($user);
        $this->assertSame($user, $this->lock->getLockedBy());
        $this->assertSame($this->lock, $result);
    }

    public function testSetGetLockedAt(): void
    {
        $date = new DateTimeImmutable('2024-01-15 10:00:00');
        $result = $this->lock->setLockedAt($date);
        $this->assertSame($date, $this->lock->getLockedAt());
        $this->assertSame($this->lock, $result);
    }

    public function testSetGetExpiresAt(): void
    {
        $date = new DateTimeImmutable('2024-01-15 10:15:00');
        $result = $this->lock->setExpiresAt($date);
        $this->assertSame($date, $this->lock->getExpiresAt());
        $this->assertSame($this->lock, $result);
    }

    public function testSetGetSessionId(): void
    {
        $result = $this->lock->setSessionId('session_abc123');
        $this->assertSame('session_abc123', $this->lock->getSessionId());
        $this->assertSame($this->lock, $result);
    }

    public function testSetGetSessionIdNull(): void
    {
        $this->lock->setSessionId('test');
        $this->lock->setSessionId(null);
        $this->assertNull($this->lock->getSessionId());
    }

    public function testRefreshExpiration(): void
    {
        // Set expiration to the past
        $this->lock->setExpiresAt(new DateTimeImmutable('-1 hour'));
        $this->assertTrue($this->lock->isExpired());

        $this->lock->refreshExpiration();
        // After refresh, should no longer be expired (15 min in future)
        $this->assertFalse($this->lock->isExpired());
        $newExpires = $this->lock->getExpiresAt();
        $diff = $newExpires->getTimestamp() - (new DateTimeImmutable())->getTimestamp();
        $this->assertGreaterThan(14 * 60 - 1, $diff);
    }

    public function testIsExpiredWhenNotExpired(): void
    {
        // Default constructor sets expiration 15 minutes in the future
        $this->assertFalse($this->lock->isExpired());
    }

    public function testIsExpiredWhenExpired(): void
    {
        $this->lock->setExpiresAt(new DateTimeImmutable('-1 minute'));
        $this->assertTrue($this->lock->isExpired());
    }
}
