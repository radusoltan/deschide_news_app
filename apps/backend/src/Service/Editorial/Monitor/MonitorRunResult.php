<?php

declare(strict_types=1);

namespace App\Service\Editorial\Monitor;

/**
 * Return value of {@see AbstractRssMonitor::fetchAndEmit()}. Immutable.
 *
 * @phpstan-type MonitorError string
 */
final readonly class MonitorRunResult
{
    /**
     * @param list<string> $errors per-source error strings ("source=slug: message")
     */
    public function __construct(
        public int $signalsEmitted,
        public int $sourcesProcessed,
        public int $sourcesSkipped,
        public array $errors,
    ) {}
}
