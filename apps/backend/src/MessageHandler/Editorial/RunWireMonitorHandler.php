<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Message\Editorial\RunWireMonitorMessage;
use App\Service\Editorial\Monitor\WireSourceMonitor;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Scheduler handler (Sprint 53 T53.9) — calls WireSourceMonitor.fetchAndEmit
 * and logs the run result. Never rethrows; failures are observed via logs.
 */
#[AsMessageHandler]
final readonly class RunWireMonitorHandler
{
    public function __construct(
        private WireSourceMonitor $monitor,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(RunWireMonitorMessage $message): void
    {
        try {
            $result = $this->monitor->fetchAndEmit();
            $this->logger->info('RunWireMonitorHandler: finished', [
                'signals_emitted' => $result->signalsEmitted,
                'sources_processed' => $result->sourcesProcessed,
                'sources_skipped' => $result->sourcesSkipped,
                'errors' => $result->errors,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('RunWireMonitorHandler: fetchAndEmit threw', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
