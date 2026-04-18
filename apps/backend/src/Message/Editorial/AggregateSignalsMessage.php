<?php

declare(strict_types=1);

namespace App\Message\Editorial;

/**
 * Emitted by {@see \App\MessageHandler\Editorial\FlushStabilizedSignalsMessageHandler}
 * once a cluster of signals sharing a topic hash has finished stabilizing
 * (Sprint 54 T54.6). Carries the opaque topic hash and the list of
 * `source_signals.id` rows that belong to the cluster at flush time.
 *
 * The handler is implemented in Sprint 54 T54.8 ({@see \App\Service\Editorial\Verification\SignalAggregator}):
 * - hydrate the signals from the DB,
 * - run Elasticsearch similarity + an LLM semantic gate,
 * - build the {@see \App\DTO\Editorial\ClaimOriginGraph},
 * - dispatch {@see VerifyClaimMessage} per confirmed cluster.
 *
 * Kept intentionally minimal (topic hash + id list) so the flush tick is
 * fast — actual signal hydration happens in the aggregator, not here.
 */
final readonly class AggregateSignalsMessage
{
    /**
     * @param list<int> $signalIds source_signals.id values belonging to the stabilized cluster
     */
    public function __construct(
        public string $topicHash,
        public array $signalIds,
    ) {}
}
