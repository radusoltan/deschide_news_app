<?php

declare(strict_types=1);

namespace App\Message\Editorial;

/**
 * Dispatched after a {@see \App\Entity\Editorial\SourceSignal} row is
 * persisted by a SourceMonitor.
 *
 * Sprint 53 scope: the handler ({@see \App\MessageHandler\Editorial\SignalIngestedHandler})
 * only logs receipt so the transport + Supervisor plumbing is exercised
 * end-to-end. Sprint 54 replaces the handler with the SignalAggregator /
 * VerificationGate entry point.
 */
final readonly class SignalIngestedMessage
{
    public function __construct(
        public int $sourceSignalId,
    ) {}
}
