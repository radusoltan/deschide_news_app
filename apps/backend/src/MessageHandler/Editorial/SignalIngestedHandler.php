<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Message\Editorial\SignalIngestedMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Sprint 53 stub. Logs receipt only — proves the `editorial_signal_ingest`
 * transport + Supervisor worker wiring (T53.8) is alive end-to-end.
 *
 * Sprint 54 replaces this handler with the real SignalAggregator dispatcher
 * that kicks off the verification layer.
 */
#[AsMessageHandler]
final readonly class SignalIngestedHandler
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function __invoke(SignalIngestedMessage $message): void
    {
        $this->logger->info('SignalIngestedHandler: received signal', [
            'source_signal_id' => $message->sourceSignalId,
            'sprint' => 53,
            'handler_contract' => 'stub-log-only',
        ]);
    }
}
