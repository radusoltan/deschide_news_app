<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\NotificationType;
use PHPUnit\Framework\TestCase;

class NotificationTypeTest extends TestCase
{
    public function testAllCases(): void
    {
        $cases = NotificationType::cases();
        $this->assertCount(9, $cases);
    }

    public function testValues(): void
    {
        $this->assertSame('article_published', NotificationType::ARTICLE_PUBLISHED->value);
        $this->assertSame('article_updated', NotificationType::ARTICLE_UPDATED->value);
        $this->assertSame('user_login', NotificationType::USER_LOGIN->value);
        $this->assertSame('user_action', NotificationType::USER_ACTION->value);
        $this->assertSame('system_error', NotificationType::SYSTEM_ERROR->value);
        $this->assertSame('job_failed', NotificationType::JOB_FAILED->value);
        $this->assertSame('article_auto_created', NotificationType::ARTICLE_AUTO_CREATED->value);
        $this->assertSame('article_translated', NotificationType::ARTICLE_TRANSLATED->value);
        $this->assertSame('press_queue_new', NotificationType::PRESS_QUEUE_NEW->value);
    }

    public function testFromValidValue(): void
    {
        $this->assertSame(NotificationType::ARTICLE_PUBLISHED, NotificationType::from('article_published'));
        $this->assertSame(NotificationType::JOB_FAILED, NotificationType::from('job_failed'));
        $this->assertSame(NotificationType::ARTICLE_AUTO_CREATED, NotificationType::from('article_auto_created'));
        $this->assertSame(NotificationType::ARTICLE_TRANSLATED, NotificationType::from('article_translated'));
    }

    public function testTryFromValidValue(): void
    {
        $this->assertSame(NotificationType::ARTICLE_PUBLISHED, NotificationType::tryFrom('article_published'));
        $this->assertSame(NotificationType::ARTICLE_UPDATED, NotificationType::tryFrom('article_updated'));
        $this->assertSame(NotificationType::USER_LOGIN, NotificationType::tryFrom('user_login'));
        $this->assertSame(NotificationType::USER_ACTION, NotificationType::tryFrom('user_action'));
        $this->assertSame(NotificationType::SYSTEM_ERROR, NotificationType::tryFrom('system_error'));
        $this->assertSame(NotificationType::JOB_FAILED, NotificationType::tryFrom('job_failed'));
        $this->assertSame(NotificationType::ARTICLE_AUTO_CREATED, NotificationType::tryFrom('article_auto_created'));
        $this->assertSame(NotificationType::ARTICLE_TRANSLATED, NotificationType::tryFrom('article_translated'));
        $this->assertSame(NotificationType::PRESS_QUEUE_NEW, NotificationType::tryFrom('press_queue_new'));
    }

    public function testFromInvalidValue(): void
    {
        $this->expectException(\ValueError::class);
        NotificationType::from('invalid');
    }

    public function testTryFromInvalidValue(): void
    {
        $this->assertNull(NotificationType::tryFrom('email_sent'));
    }
}
