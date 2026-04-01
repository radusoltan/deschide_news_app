<?php

declare(strict_types=1);

namespace App\Tests\Unit\Message;

use App\Enum\TranslatableEntityType;
use App\Message\TranslateEntityMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(TranslateEntityMessage::class)]
class TranslateEntityMessageTest extends TestCase
{
    #[Test]
    public function constructWithDefaults(): void
    {
        $msg = new TranslateEntityMessage(
            entityType: TranslatableEntityType::CATEGORY,
            entityId: 5,
        );

        $this->assertSame(TranslatableEntityType::CATEGORY, $msg->entityType);
        $this->assertSame(5, $msg->entityId);
        $this->assertSame(['ru', 'en'], $msg->locales);
        $this->assertFalse($msg->force);
    }

    #[Test]
    public function constructWithCustomValues(): void
    {
        $msg = new TranslateEntityMessage(
            entityType: TranslatableEntityType::AUTHOR,
            entityId: 42,
            locales: ['en'],
            force: true,
        );

        $this->assertSame(TranslatableEntityType::AUTHOR, $msg->entityType);
        $this->assertSame(42, $msg->entityId);
        $this->assertSame(['en'], $msg->locales);
        $this->assertTrue($msg->force);
    }

    #[Test]
    public function messageIsReadonly(): void
    {
        $msg = new TranslateEntityMessage(
            entityType: TranslatableEntityType::CATEGORY,
            entityId: 1,
        );

        $reflection = new \ReflectionClass($msg);
        $this->assertTrue($reflection->isReadOnly());
    }
}
