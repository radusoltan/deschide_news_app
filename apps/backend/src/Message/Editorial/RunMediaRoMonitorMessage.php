<?php

declare(strict_types=1);

namespace App\Message\Editorial;

/**
 * Scheduler-emitted message (Sprint 53 T53.9) that triggers one run of
 * {@see \App\Service\Editorial\Monitor\MediaRoSourceMonitor}. No payload —
 * the handler invokes `fetchAndEmit()` unconditionally.
 */
final readonly class RunMediaRoMonitorMessage
{
}
