<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Entity\Editorial\SourceSignal;
use App\Message\Editorial\AggregateSignalsMessage;
use App\Message\Editorial\VerifyClaimMessage;
use App\Repository\AppSettingRepository;
use App\Repository\Editorial\SourceSignalRepository;
use App\Service\Editorial\TopicHashResolver;
use App\Service\Editorial\Verification\SignalAggregator;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handler for {@see AggregateSignalsMessage} (Sprint 54 T54.8).
 *
 * Hydrates the stabilized signal ids, asks {@see SignalAggregator} to
 * group them by ES-similarity overlap and confirm via the semantic LLM
 * gate, then dispatches one {@see VerifyClaimMessage} per confirmed
 * graph. Log-and-swallow failure contract matches the other verification
 * handlers in Sprint 54.
 */
#[AsMessageHandler]
final readonly class AggregateSignalsMessageHandler
{
    public function __construct(
        private SignalAggregator $aggregator,
        private MessageBusInterface $messageBus,
        private AppSettingRepository $appSettings,
        private SourceSignalRepository $signalRepository,
        private TopicHashResolver $topicHashResolver,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(AggregateSignalsMessage $message): void
    {
        // Emergency circuit breaker (T56.02, ADR-022 D5). Short-circuits
        // BEFORE any LLM call or aggregator invocation so mid-run halts work
        // even with in-flight messages already dispatched to the queue.
        if ($this->appSettings->getBool('editorial.emergency_halt', false)) {
            $this->logger->info('emergency_halt.triggered', [
                'handler' => self::class,
                'message_class' => $message::class,
                'message_id_hint' => $message->topicHash,
            ]);

            return;
        }

        try {
            $graphs = $this->aggregator->aggregate($message->topicHash, $message->signalIds);

            // Resolve the cluster's topic once per dispatch batch. All graphs
            // in this batch share the same topic_hash (same stabilization
            // bucket), so a single LLM round-trip covers the whole fan-out.
            // T56.04 / ADR-022 D4: unblocks VerifyClaimMessageHandler's
            // developing-story continuation, which was dead code in S55 due
            // to topicId always arriving null.
            $topicId = null;
            if ($graphs !== []) {
                $representativeSignal = $this->hydrateRepresentativeSignal($message->signalIds);
                if ($representativeSignal !== null) {
                    $topicId = $this->topicHashResolver->resolve($message->topicHash, $representativeSignal);
                }
            }

            foreach ($graphs as $graph) {
                $this->messageBus->dispatch(new VerifyClaimMessage(
                    topicHash: $graph->topicHash,
                    graphArray: $graph->toArray(),
                    signalIds: $message->signalIds,
                    topicId: $topicId,
                ));
            }

            if ($graphs === []) {
                $this->logger->info('AggregateSignalsMessageHandler: aggregation produced no graphs', [
                    'topic_hash' => $message->topicHash,
                    'signals_in' => \count($message->signalIds),
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('AggregateSignalsMessageHandler: aggregation threw', [
                'topic_hash' => $message->topicHash,
                'signals_in' => \count($message->signalIds),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Pick the first signal id in the batch that still hydrates from the DB.
     * Signals may be deleted between stabilization and aggregation, so we
     * walk the list instead of trusting index 0 blindly.
     *
     * @param list<int> $signalIds
     */
    private function hydrateRepresentativeSignal(array $signalIds): ?SourceSignal
    {
        foreach ($signalIds as $id) {
            $signal = $this->signalRepository->find($id);
            if ($signal !== null) {
                return $signal;
            }
        }

        return null;
    }
}
