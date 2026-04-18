<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Message\Editorial\ExtractSourceAttributionMessage;
use App\Message\Editorial\SignalIngestedMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Received once per freshly persisted {@see \App\Entity\Editorial\SourceSignal}.
 *
 * Sprint 53 scope was log-only — it proved the transport + Supervisor
 * worker wiring was alive. Sprint 54 T54.7 upgrades it to the real
 * verification-layer entry point: dispatch
 * {@see ExtractSourceAttributionMessage} so the signal can be LLM-enriched
 * and registered in the stabilization buffer without blocking the ingest
 * worker.
 *
 * Behaviour stays gated by `editorial.pipeline.enabled` upstream at the
 * scheduler provider — this handler never runs unless a monitor already
 * produced a signal, which in turn requires the pipeline master switch.
 */
#[AsMessageHandler]
final readonly class SignalIngestedHandler
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(SignalIngestedMessage $message): void
    {
        $this->logger->debug('SignalIngestedHandler: forwarding to source-attribution extractor', [
            'source_signal_id' => $message->sourceSignalId,
        ]);

        $this->messageBus->dispatch(new ExtractSourceAttributionMessage(
            sourceSignalId: $message->sourceSignalId,
        ));
    }
}
