<?php

declare(strict_types=1);

namespace App\Message\Editorial;

/**
 * Carries the claim-origin graph for one confirmed cluster from
 * {@see \App\Service\Editorial\Verification\SignalAggregator} (Sprint 54
 * T54.8) to the {@see \App\Service\Editorial\Verification\VerificationGate}
 * (Sprint 54 T54.9) that rules on the D3 publication matrix.
 *
 * The graph travels as its serialized `toArray()` form so the message is
 * JSON-safe across the messenger transport; the handler re-hydrates via
 * a builder helper.
 *
 * Skeleton created in T54.8 so the aggregator compiles end-to-end. T54.9
 * adds the matching handler.
 */
final readonly class VerifyClaimMessage
{
    /**
     * @param array<string, mixed> $graphArray Result of ClaimOriginGraph::toArray()
     * @param list<int> $signalIds source_signals.id values covered by this claim
     */
    public function __construct(
        public string $topicHash,
        public array $graphArray,
        public array $signalIds,
    ) {}
}
