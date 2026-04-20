<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Message\Editorial\AggregateSignalsMessage;
use App\Message\Editorial\VerifyClaimMessage;
use App\Repository\AppSettingRepository;
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

            foreach ($graphs as $graph) {
                $this->messageBus->dispatch(new VerifyClaimMessage(
                    topicHash: $graph->topicHash,
                    graphArray: $graph->toArray(),
                    signalIds: $message->signalIds,
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
}
