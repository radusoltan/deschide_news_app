<?php

declare(strict_types=1);

namespace App\Message\Topic;

/**
 * Dispatched after a PressRelease is persisted to trigger async topic detection.
 */
final readonly class DetectTopicsForPressReleaseMessage
{
    public function __construct(
        public int $pressReleaseId,
    ) {
    }
}
