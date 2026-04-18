<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Message\Editorial\RunMediaRuMonitorMessage;
use App\Service\Editorial\Monitor\MediaRuSourceMonitor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Scheduler handler (Sprint 54 T54.5) — calls MediaRuSourceMonitor.fetchAndEmit
 * and logs the run result. Never rethrows; failures are observed via logs.
 */
#[AsMessageHandler]
final readonly class RunMediaRuMonitorHandler
{
    public function __construct(
        private MediaRuSourceMonitor $monitor,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(RunMediaRuMonitorMessage $message): void
    {
        try {
            $result = $this->monitor->fetchAndEmit();
            $this->logger->info('RunMediaRuMonitorHandler: finished', [
                'signals_emitted' => $result->signalsEmitted,
                'sources_processed' => $result->sourcesProcessed,
                'sources_skipped' => $result->sourcesSkipped,
                'errors' => $result->errors,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('RunMediaRuMonitorHandler: fetchAndEmit threw', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
