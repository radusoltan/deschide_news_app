<?php

declare(strict_types=1);

namespace App\Tests\Unit\Message\NotebookLM;

use App\Message\NotebookLM\TriggerTopicNotebookSyncMessage;
use PHPUnit\Framework\TestCase;

class TriggerTopicNotebookSyncMessageTest extends TestCase
{
    public function testMessageCanBeInstantiated(): void
    {
        $message = new TriggerTopicNotebookSyncMessage();
        self::assertInstanceOf(TriggerTopicNotebookSyncMessage::class, $message);
    }
}
