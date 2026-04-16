<?php

declare(strict_types=1);

namespace App\Tests\Unit\Message\Topic;

use App\Enum\BriefingCadence;
use App\Message\Topic\TriggerTopicBriefingRunMessage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class TriggerTopicBriefingRunMessageTest extends TestCase
{
    #[Test]
    public function itStoresCadence(): void
    {
        $msg = new TriggerTopicBriefingRunMessage(BriefingCadence::DAILY);

        $this->assertSame(BriefingCadence::DAILY, $msg->cadence);
    }

    #[Test]
    public function itIsReadonly(): void
    {
        $msg = new TriggerTopicBriefingRunMessage(BriefingCadence::HOURLY);

        $ref = new \ReflectionClass($msg);
        $this->assertTrue($ref->isReadOnly());
    }

    #[Test]
    public function itSupportsAllCadences(): void
    {
        foreach (BriefingCadence::cases() as $cadence) {
            $msg = new TriggerTopicBriefingRunMessage($cadence);
            $this->assertSame($cadence, $msg->cadence);
        }
    }
}
