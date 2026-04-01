<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\AdminNotification;
use App\Entity\User;
use App\Enum\NotificationImportance;
use App\Enum\NotificationType;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

class AdminNotificationTest extends TestCase
{
    private AdminNotification $notification;

    protected function setUp(): void
    {
        $this->notification = new AdminNotification();
    }

    public function testConstructorSetsId(): void
    {
        $this->assertInstanceOf(Uuid::class, $this->notification->getId());
    }

    public function testConstructorSetsCreatedAt(): void
    {
        $this->assertInstanceOf(DateTimeImmutable::class, $this->notification->getCreatedAt());
    }

    public function testDefaultValues(): void
    {
        $this->assertSame(NotificationImportance::MEDIUM, $this->notification->getImportance());
        $this->assertNull($this->notification->getMessage());
        $this->assertNull($this->notification->getRelatedEntityType());
        $this->assertNull($this->notification->getRelatedEntityId());
        $this->assertNull($this->notification->getActionUrl());
        $this->assertFalse($this->notification->isRead());
        $this->assertNull($this->notification->getReadAt());
    }

    public function testSetGetRecipientUser(): void
    {
        $user = new User();
        $result = $this->notification->setRecipientUser($user);
        $this->assertSame($user, $this->notification->getRecipientUser());
        $this->assertSame($this->notification, $result);
    }

    public function testSetGetType(): void
    {
        $result = $this->notification->setType(NotificationType::ARTICLE_PUBLISHED);
        $this->assertSame(NotificationType::ARTICLE_PUBLISHED, $this->notification->getType());
        $this->assertSame($this->notification, $result);
    }

    public function testSetGetTypeAllValues(): void
    {
        foreach (NotificationType::cases() as $type) {
            $this->notification->setType($type);
            $this->assertSame($type, $this->notification->getType());
        }
    }

    public function testSetGetImportance(): void
    {
        $result = $this->notification->setImportance(NotificationImportance::HIGH);
        $this->assertSame(NotificationImportance::HIGH, $this->notification->getImportance());
        $this->assertSame($this->notification, $result);
    }

    public function testSetGetImportanceAllValues(): void
    {
        foreach (NotificationImportance::cases() as $importance) {
            $this->notification->setImportance($importance);
            $this->assertSame($importance, $this->notification->getImportance());
        }
    }

    public function testSetGetTitle(): void
    {
        $result = $this->notification->setTitle('New Article Published');
        $this->assertSame('New Article Published', $this->notification->getTitle());
        $this->assertSame($this->notification, $result);
    }

    public function testSetGetMessage(): void
    {
        $result = $this->notification->setMessage('Article "Test" was published');
        $this->assertSame('Article "Test" was published', $this->notification->getMessage());
        $this->assertSame($this->notification, $result);
    }

    public function testSetGetMessageNull(): void
    {
        $this->notification->setMessage('test');
        $this->notification->setMessage(null);
        $this->assertNull($this->notification->getMessage());
    }

    public function testSetGetRelatedEntityType(): void
    {
        $result = $this->notification->setRelatedEntityType('article');
        $this->assertSame('article', $this->notification->getRelatedEntityType());
        $this->assertSame($this->notification, $result);
    }

    public function testSetGetRelatedEntityId(): void
    {
        $result = $this->notification->setRelatedEntityId(42);
        $this->assertSame(42, $this->notification->getRelatedEntityId());
        $this->assertSame($this->notification, $result);
    }

    public function testSetGetActionUrl(): void
    {
        $result = $this->notification->setActionUrl('/admin/articles/42/edit');
        $this->assertSame('/admin/articles/42/edit', $this->notification->getActionUrl());
        $this->assertSame($this->notification, $result);
    }

    public function testSetGetIsRead(): void
    {
        $result = $this->notification->setIsRead(true);
        $this->assertTrue($this->notification->isRead());
        $this->assertSame($this->notification, $result);
    }

    public function testSetGetReadAt(): void
    {
        $date = new DateTimeImmutable('2024-06-15 10:00:00');
        $result = $this->notification->setReadAt($date);
        $this->assertSame($date, $this->notification->getReadAt());
        $this->assertSame($this->notification, $result);
    }

    public function testMarkAsRead(): void
    {
        $result = $this->notification->markAsRead();
        $this->assertTrue($this->notification->isRead());
        $this->assertInstanceOf(DateTimeImmutable::class, $this->notification->getReadAt());
        $this->assertSame($this->notification, $result);
    }

    public function testMarkAsReadSetsTimestamp(): void
    {
        $before = new DateTimeImmutable();
        $this->notification->markAsRead();
        $readAt = $this->notification->getReadAt();
        $this->assertGreaterThanOrEqual($before->getTimestamp(), $readAt->getTimestamp());
    }

    public function testUniqueIds(): void
    {
        $notification2 = new AdminNotification();
        $this->assertFalse($this->notification->getId()->equals($notification2->getId()));
    }
}
