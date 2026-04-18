<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Dto\Editorial\VerificationVerdict;
use App\Entity\Article;
use App\Enum\Editorial\VerdictType;
use App\Message\Editorial\WriteFlashMessage;
use App\Repository\Editorial\SourceSignalRepository;
use App\Repository\TopicRepository;
use App\Service\Editorial\Writer\FlashWriter;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Async handler for {@see WriteFlashMessage} (Sprint 55 T55.3) running on the
 * `editorial_flash` transport. Resolves the SourceSignal cluster + Topic from
 * IDs, reconstructs a lightweight {@see VerificationVerdict}, and delegates to
 * {@see FlashWriter::write()}.
 *
 * The handler is defensive: missing primary signal, unknown verdict type or
 * empty writer output all land as logged no-ops rather than exceptions, so
 * Messenger does not retry stale messages forever.
 */
#[AsMessageHandler]
class WriteFlashMessageHandler
{
    public function __construct(
        private readonly FlashWriter $flashWriter,
        private readonly SourceSignalRepository $signalRepository,
        private readonly TopicRepository $topicRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(WriteFlashMessage $message): ?Article
    {
        $primary = $this->signalRepository->find($message->primarySignalId);
        if ($primary === null) {
            $this->logger->warning('write_flash_primary_signal_missing', [
                'primary_signal_id' => $message->primarySignalId,
            ]);

            return null;
        }

        $supporting = [];
        foreach ($message->supportingSignalIds as $id) {
            $signal = $this->signalRepository->find($id);
            if ($signal !== null) {
                $supporting[] = $signal;
            }
        }

        $verdictType = VerdictType::tryFrom($message->verdictType);
        if ($verdictType === null) {
            $this->logger->error('write_flash_unknown_verdict_type', [
                'verdict_type' => $message->verdictType,
                'primary_signal_id' => $message->primarySignalId,
            ]);

            return null;
        }

        $topic = $message->topicId !== null ? $this->topicRepository->find($message->topicId) : null;

        // Reconstruct a minimal verdict — the handler only needs the type +
        // confidence hook; the full reasoning arrived from the verification
        // gate and is already persisted on the signal's claim_graph_snapshot.
        $verdict = new VerificationVerdict(
            type: $verdictType,
            reasoning: 'dispatched-from-verify-claim-handler',
            confidence: $this->extractConfidence($primary),
        );

        return $this->flashWriter->write($primary, $supporting, $verdict, $topic);
    }

    private function extractConfidence(\App\Entity\Editorial\SourceSignal $signal): float
    {
        $snapshot = $signal->getClaimGraphSnapshot();
        if (!\is_array($snapshot)) {
            return 1.0;
        }
        $confidence = $snapshot['verdict_confidence'] ?? $snapshot['confidence'] ?? null;

        return \is_numeric($confidence) ? (float) $confidence : 1.0;
    }
}
