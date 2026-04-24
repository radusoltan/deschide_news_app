<?php

declare(strict_types=1);

namespace App\Message\Editorial;

/**
 * Dispatched by {@see \App\MessageHandler\Editorial\SignalIngestedHandler}
 * after a freshly persisted {@see \App\Entity\Editorial\SourceSignal} row
 * is ready for LLM enrichment (Sprint 54 T54.7, ADR-020 D2).
 *
 * Handled by {@see \App\MessageHandler\Editorial\ExtractSourceAttributionMessageHandler},
 * which runs {@see \App\Service\Editorial\Verification\SourceAttributionExtractor}
 * on the signal and then registers it in the
 * {@see \App\Service\Editorial\Verification\SignalStabilizationBuffer}.
 *
 * Carries only the id so the work can be safely picked up by a different
 * worker than the one that ingested the signal.
 */
final readonly class ExtractSourceAttributionMessage
{
    public function __construct(
        public int $sourceSignalId,
    ) {}
}
