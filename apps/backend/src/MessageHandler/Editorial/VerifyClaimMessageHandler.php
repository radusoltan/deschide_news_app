<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Dto\Editorial\ClaimOriginGraph;
use App\Entity\Editorial\SourceClaimHistory;
use App\Entity\Editorial\SourceSignal;
use App\Enum\ClaimOutcome;
use App\Message\Editorial\VerifyClaimMessage;
use App\Repository\Editorial\SourceSignalRepository;
use App\Service\Editorial\Verification\VerificationGate;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler for {@see VerifyClaimMessage} (Sprint 54 T54.9).
 *
 * Flow:
 *  1. Hydrate signals from the repository. Missing → warn + return.
 *  2. Rehydrate the ClaimOriginGraph from the message's graphArray payload.
 *  3. Ask {@see VerificationGate} for a verdict (D3 matrix + LLM sanity).
 *  4. Persist the snapshot (graph + verdict) onto each signal's
 *     `claim_graph_snapshot` column.
 *  5. Insert one {@see SourceClaimHistory} row with outcome UNRESOLVED so
 *     Sprint 55's rolling-trust updater can later mark it
 *     CONFIRMED/INFIRMED based on downstream editorial confirmation.
 *
 * Log-and-swallow failure contract matches the other Sprint 54 verification
 * handlers.
 */
#[AsMessageHandler]
final readonly class VerifyClaimMessageHandler
{
    public function __construct(
        private SourceSignalRepository $sourceSignalRepository,
        private VerificationGate $gate,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(VerifyClaimMessage $message): void
    {
        try {
            /** @var list<SourceSignal> $signals */
            $signals = [];
            foreach ($message->signalIds as $id) {
                $signal = $this->sourceSignalRepository->find($id);
                if ($signal !== null) {
                    $signals[] = $signal;
                }
            }

            if ($signals === []) {
                $this->logger->warning('VerifyClaimMessageHandler: no signals hydrated', [
                    'topic_hash' => $message->topicHash,
                    'requested_ids' => \count($message->signalIds),
                ]);

                return;
            }

            $graph = ClaimOriginGraph::fromArray($message->graphArray);
            $verdict = $this->gate->rule($graph, $signals);

            $snapshot = [
                'graph' => $graph->toArray(),
                'verdict' => $verdict->type->value,
                'verdict_reasoning' => $verdict->reasoning,
                'llm_override' => $verdict->llmOverride,
                'llm_sanity_skipped' => $verdict->llmSanitySkipped,
                'escalation_keyword' => $verdict->escalationKeyword,
                'confidence' => $verdict->confidence,
                'decided_at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
                'decided_by' => 'verification_gate_v1',
            ];

            foreach ($signals as $signal) {
                $signal->setClaimGraphSnapshot($snapshot);
            }

            $primarySignal = $this->selectPrimarySignal($signals);
            $history = new SourceClaimHistory(
                verifiedSource: $primarySignal->getVerifiedSource(),
                claimText: $this->deriveClaimText($primarySignal),
                outcome: ClaimOutcome::UNRESOLVED,
            );
            $this->entityManager->persist($history);
            $this->entityManager->flush();

            $this->logger->info('verification_verdict_decided', [
                'topic_hash' => $message->topicHash,
                'verdict' => $verdict->type->value,
                'signals' => \count($signals),
                'llm_override' => $verdict->llmOverride,
                'llm_sanity_skipped' => $verdict->llmSanitySkipped,
                'escalation_keyword' => $verdict->escalationKeyword,
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('VerifyClaimMessageHandler: verdict flow threw', [
                'topic_hash' => $message->topicHash,
                'error' => $e->getMessage(),
                'trace' => mb_substr($e->getTraceAsString(), 0, 500),
            ]);
        }
    }

    /**
     * @param list<SourceSignal> $signals
     */
    private function selectPrimarySignal(array $signals): SourceSignal
    {
        // Prefer the tier-1 node if any; else the earliest captured signal.
        $tier1 = null;
        foreach ($signals as $signal) {
            if ($signal->getVerifiedSource()->getTier() === 1) {
                $tier1 = $signal;
                break;
            }
        }
        if ($tier1 !== null) {
            return $tier1;
        }

        $sorted = $signals;
        usort(
            $sorted,
            static fn (SourceSignal $a, SourceSignal $b): int => $a->getCapturedAt() <=> $b->getCapturedAt(),
        );

        return $sorted[0];
    }

    private function deriveClaimText(SourceSignal $signal): string
    {
        // Sprint 54 uses the signal title verbatim; Sprint 55+ will
        // LLM-synthesize a cluster-level claim summary.
        $title = trim($signal->getTitle());

        return $title !== '' ? $title : sprintf('(empty signal id=%d)', $signal->getId() ?? 0);
    }
}
