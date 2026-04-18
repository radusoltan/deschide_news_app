<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Message\Editorial\RunMediaRoMonitorMessage;
use App\Service\Editorial\Monitor\MediaRoSourceMonitor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Scheduler handler (Sprint 53 T53.9) — calls MediaRoSourceMonitor.fetchAndEmit
 * and logs the run result. Never rethrows; failures are observed via logs.
 */
#[AsMessageHandler]
final readonly class RunMediaRoMonitorHandler
{
    public function __construct(
        private MediaRoSourceMonitor $monitor,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(RunMediaRoMonitorMessage $message): void
    {
        try {
            $result = $this->monitor->fetchAndEmit();
            $this->logger->info('RunMediaRoMonitorHandler: finished', [
                'signals_emitted' => $result->signalsEmitted,
                'sources_processed' => $result->sourcesProcessed,
                'sources_skipped' => $result->sourcesSkipped,
                'errors' => $result->errors,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('RunMediaRoMonitorHandler: fetchAndEmit threw', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
