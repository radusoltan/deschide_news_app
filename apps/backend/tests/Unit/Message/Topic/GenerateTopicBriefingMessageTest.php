<?php

declare(strict_types=1);

namespace App\Tests\Unit\Message\Topic;

use App\Enum\BriefingCadence;
use App\Message\Topic\GenerateTopicBriefingMessage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class GenerateTopicBriefingMessageTest extends TestCase
{
    #[Test]
    public function itStoresAllFields(): void
    {
        $msg = new GenerateTopicBriefingMessage(
            topicId: 42,
            cadence: BriefingCadence::WEEKLY,
            periodFrom: '2026-04-09T00:00:00+00:00',
            periodTo: '2026-04-16T00:00:00+00:00',
        );

        $this->assertSame(42, $msg->topicId);
        $this->assertSame(BriefingCadence::WEEKLY, $msg->cadence);
        $this->assertSame('2026-04-09T00:00:00+00:00', $msg->periodFrom);
        $this->assertSame('2026-04-16T00:00:00+00:00', $msg->periodTo);
    }

    #[Test]
    public function itIsReadonly(): void
    {
        $msg = new GenerateTopicBriefingMessage(1, BriefingCadence::DAILY, '', '');

        $ref = new \ReflectionClass($msg);
        $this->assertTrue($ref->isReadOnly());
    }
}
