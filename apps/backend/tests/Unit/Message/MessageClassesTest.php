<?php

declare(strict_types=1);

namespace App\Tests\Unit\Message;

use App\Message\CheckOrphanedTagsMessage;
use App\Message\CleanupUnusedTagsMessage;
use App\Message\PageViewEvent;
use App\Message\PublishScheduledArticles;
use App\Message\RecalculateTagCountsMessage;
use App\Message\SessionEndEvent;
use App\Message\ShortLinkClickMessage;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MessageClassesTest extends TestCase
{
    // --- PageViewEvent ---

    #[Test]
    public function pageViewEventStoresAllProperties(): void
    {
        $timestamp = new DateTimeImmutable();
        $event = new PageViewEvent(
            articleId: 42,
            visitorId: 'visitor-abc',
            ipAddress: '192.168.1.1',
            userAgent: 'Mozilla/5.0',
            referrer: 'https://google.com',
            timestamp: $timestamp,
            categoryId: 5,
            sessionId: 'session-xyz'
        );

        $this->assertSame(42, $event->articleId);
        $this->assertSame('visitor-abc', $event->visitorId);
        $this->assertSame('192.168.1.1', $event->ipAddress);
        $this->assertSame('Mozilla/5.0', $event->userAgent);
        $this->assertSame('https://google.com', $event->referrer);
        $this->assertSame($timestamp, $event->timestamp);
        $this->assertSame(5, $event->categoryId);
        $this->assertSame('session-xyz', $event->sessionId);
    }

    #[Test]
    public function pageViewEventHasNullableDefaults(): void
    {
        $timestamp = new DateTimeImmutable();
        $event = new PageViewEvent(
            articleId: 1,
            visitorId: 'v1',
            ipAddress: null,
            userAgent: null,
            referrer: null,
            timestamp: $timestamp
        );

        $this->assertNull($event->ipAddress);
        $this->assertNull($event->userAgent);
        $this->assertNull($event->referrer);
        $this->assertNull($event->categoryId);
        $this->assertNull($event->sessionId);
    }

    // --- SessionEndEvent ---

    #[Test]
    public function sessionEndEventStoresAllProperties(): void
    {
        $start = new DateTimeImmutable('-1 hour');
        $end = new DateTimeImmutable();
        $event = new SessionEndEvent(
            sessionId: 'sess-123',
            visitorId: 'visitor-456',
            startedAt: $start,
            endedAt: $end,
            pageCount: 5,
            duration: 3600,
            ipAddress: '10.0.0.1',
            userAgent: 'Chrome',
            referrer: 'https://facebook.com'
        );

        $this->assertSame('sess-123', $event->sessionId);
        $this->assertSame('visitor-456', $event->visitorId);
        $this->assertSame($start, $event->startedAt);
        $this->assertSame($end, $event->endedAt);
        $this->assertSame(5, $event->pageCount);
        $this->assertSame(3600, $event->duration);
        $this->assertSame('10.0.0.1', $event->ipAddress);
        $this->assertSame('Chrome', $event->userAgent);
        $this->assertSame('https://facebook.com', $event->referrer);
    }

    #[Test]
    public function sessionEndEventHasNullableDefaults(): void
    {
        $start = new DateTimeImmutable();
        $end = new DateTimeImmutable();
        $event = new SessionEndEvent(
            sessionId: 's1',
            visitorId: 'v1',
            startedAt: $start,
            endedAt: $end,
            pageCount: 1,
            duration: 60
        );

        $this->assertNull($event->ipAddress);
        $this->assertNull($event->userAgent);
        $this->assertNull($event->referrer);
    }

    // --- ShortLinkClickMessage ---

    #[Test]
    public function shortLinkClickMessageStoresAllProperties(): void
    {
        $message = new ShortLinkClickMessage(
            shortLinkId: 99,
            ipAddress: '192.168.0.1',
            userAgent: 'Firefox',
            referrer: 'https://twitter.com'
        );

        $this->assertSame(99, $message->shortLinkId);
        $this->assertSame('192.168.0.1', $message->ipAddress);
        $this->assertSame('Firefox', $message->userAgent);
        $this->assertSame('https://twitter.com', $message->referrer);
    }

    #[Test]
    public function shortLinkClickMessageHandlesNulls(): void
    {
        $message = new ShortLinkClickMessage(
            shortLinkId: 1,
            ipAddress: null,
            userAgent: null,
            referrer: null
        );

        $this->assertSame(1, $message->shortLinkId);
        $this->assertNull($message->ipAddress);
        $this->assertNull($message->userAgent);
        $this->assertNull($message->referrer);
    }

    // --- RecalculateTagCountsMessage ---

    #[Test]
    public function recalculateTagCountsMessageDefaultsToNull(): void
    {
        $message = new RecalculateTagCountsMessage();

        $this->assertNull($message->getTagId());
    }

    #[Test]
    public function recalculateTagCountsMessageAcceptsSpecificId(): void
    {
        $message = new RecalculateTagCountsMessage(42);

        $this->assertSame(42, $message->getTagId());
    }

    // --- CheckOrphanedTagsMessage ---

    #[Test]
    public function checkOrphanedTagsMessageDefaultsToEmptyArray(): void
    {
        $message = new CheckOrphanedTagsMessage();

        $this->assertSame([], $message->getTagIds());
    }

    #[Test]
    public function checkOrphanedTagsMessageAcceptsSpecificIds(): void
    {
        $message = new CheckOrphanedTagsMessage([1, 2, 3]);

        $this->assertSame([1, 2, 3], $message->getTagIds());
    }

    // --- PublishScheduledArticles ---

    #[Test]
    public function publishScheduledArticlesCanBeInstantiated(): void
    {
        $message = new PublishScheduledArticles();

        $this->assertInstanceOf(PublishScheduledArticles::class, $message);
    }

    // --- CleanupUnusedTagsMessage ---

    #[Test]
    public function cleanupUnusedTagsMessageHasDefaults(): void
    {
        $message = new CleanupUnusedTagsMessage();

        $this->assertSame(30, $message->getDaysOld());
        $this->assertFalse($message->isDryRun());
    }

    #[Test]
    public function cleanupUnusedTagsMessageAcceptsCustomValues(): void
    {
        $message = new CleanupUnusedTagsMessage(daysOld: 90, dryRun: true);

        $this->assertSame(90, $message->getDaysOld());
        $this->assertTrue($message->isDryRun());
    }
}
