<?php

declare(strict_types=1);

namespace App\Message\NotebookLM;

/**
 * Marker message to trigger NotebookLM topic notebook sync via scheduler.
 */
final readonly class TriggerTopicNotebookSyncMessage
{
}
