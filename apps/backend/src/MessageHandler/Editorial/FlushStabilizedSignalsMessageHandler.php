<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Message\Editorial\AggregateSignalsMessage;
use App\Message\Editorial\FlushStabilizedSignalsMessage;
use App\Service\Editorial\Verification\SignalStabilizationBuffer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Scheduler tick handler (Sprint 54 T54.6). Called every 10s while
 * `editorial.pipeline.enabled=true`: scans the stabilization buffer, pops
 * any signals whose cluster has finished stabilizing, and dispatches one
 * {@see AggregateSignalsMessage} per cluster.
 *
 * Failure contract matches the monitor handlers (Sprint 53): never rethrow;
 * log and return so the scheduler transport doesn't fall into a retry loop.
 */
#[AsMessageHandler]
final readonly class FlushStabilizedSignalsMessageHandler
{
    public function __construct(
        private SignalStabilizationBuffer $buffer,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(FlushStabilizedSignalsMessage $message): void
    {
        try {
            $clusters = $this->buffer->flushStabilized();

            foreach ($clusters as $topicHash => $signalIds) {
                $this->messageBus->dispatch(new AggregateSignalsMessage($topicHash, $signalIds));
            }

            if ($clusters !== []) {
                $this->logger->info('FlushStabilizedSignalsMessageHandler: dispatched aggregate messages', [
                    'clusters_dispatched' => \count($clusters),
                    'signals_total' => array_sum(array_map('\count', $clusters)),
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('FlushStabilizedSignalsMessageHandler: flush threw', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
