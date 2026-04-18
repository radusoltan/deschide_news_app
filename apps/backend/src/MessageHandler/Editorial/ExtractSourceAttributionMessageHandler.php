<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Message\Editorial\ExtractSourceAttributionMessage;
use App\Repository\Editorial\SourceSignalRepository;
use App\Service\Ai\TierResolver;
use App\Service\Editorial\Verification\SignalStabilizationBuffer;
use App\Service\Editorial\Verification\SourceAttributionExtractor;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for {@see ExtractSourceAttributionMessage} (Sprint 54 T54.7).
 *
 * Flow:
 *  1. Hydrate the signal from the repository. Missing row → warn + return.
 *  2. If `agent.source_attribution.enabled` is true, run the extractor and
 *     persist whichever fields it returned. Empty result is still a valid
 *     success path (the LLM reported no attribution).
 *  3. Regardless of extraction outcome, register the signal in the
 *     stabilization buffer so the cluster-flush tick (T54.6) picks it up.
 *
 * Topic hash for the buffer is `md5($signal->getTitle())` in Sprint 54 —
 * intentionally coarse. Sprint 55+ will swap in a proper semantic claim
 * hash.
 *
 * Matches the log-and-swallow failure contract of the Sprint 53 monitor
 * handlers: never rethrow; dispatchers (scheduler, upstream message bus)
 * don't need to retry because the extractor already fails open.
 */
#[AsMessageHandler]
final readonly class ExtractSourceAttributionMessageHandler
{
    public function __construct(
        private SourceSignalRepository $sourceSignalRepository,
        private SourceAttributionExtractor $extractor,
        private TierResolver $tierResolver,
        private SignalStabilizationBuffer $buffer,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ExtractSourceAttributionMessage $message): void
    {
        $signal = $this->sourceSignalRepository->find($message->sourceSignalId);
        if ($signal === null) {
            $this->logger->warning('ExtractSourceAttributionMessageHandler: signal not found', [
                'source_signal_id' => $message->sourceSignalId,
            ]);

            return;
        }

        $agentEnabled = $this->tierResolver->isEnabled(SourceAttributionExtractor::AGENT_ID);

        if ($agentEnabled) {
            try {
                $result = $this->extractor->extract($signal);

                if (!$result->isEmpty()) {
                    $signal->setSourceAttribution($result->sourceAttribution);
                    $signal->setSourceLinksOut($result->linksOut);
                    $this->entityManager->flush();

                    $this->logger->info('ExtractSourceAttributionMessageHandler: persisted attribution', [
                        'source_signal_id' => $signal->getId(),
                        'has_attribution' => $result->sourceAttribution !== null,
                        'links_out_count' => \count($result->linksOut),
                    ]);
                }
            } catch (\Throwable $e) {
                $this->logger->error('ExtractSourceAttributionMessageHandler: extractor threw', [
                    'source_signal_id' => $signal->getId(),
                    'error' => $e->getMessage(),
                ]);
                // fall through — still buffer the signal
            }
        } else {
            $this->logger->debug('ExtractSourceAttributionMessageHandler: agent disabled, bypassing extract', [
                'source_signal_id' => $signal->getId(),
            ]);
        }

        $this->buffer->add(
            $this->computeTopicHash($signal->getTitle()),
            $signal->getId() ?? 0,
            $signal->getCapturedAt(),
        );
    }

    /**
     * Sprint 54 uses a raw title-MD5 — see T54.7 orchestrator comment.
     * Swap for semantic hashing in Sprint 55+.
     */
    private function computeTopicHash(string $title): string
    {
        return md5(mb_strtolower(trim($title)));
    }
}
