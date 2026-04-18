<?php

declare(strict_types=1);

namespace App\Message\Editorial;

/**
 * Scheduler-emitted tick (Sprint 54 T54.6) that asks
 * {@see \App\MessageHandler\Editorial\FlushStabilizedSignalsMessageHandler}
 * to scan the Redis stabilization buffer and dispatch one
 * {@see AggregateSignalsMessage} per cluster that has finished stabilizing.
 *
 * No payload — the handler reads the window size from
 * `editorial.stabilization_window_seconds` and the `now` clock from the
 * handler itself.
 */
final readonly class FlushStabilizedSignalsMessage
{
}
