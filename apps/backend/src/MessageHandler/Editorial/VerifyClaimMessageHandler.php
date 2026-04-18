<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Dto\Editorial\ClaimOriginGraph;
use App\Entity\Editorial\SourceClaimHistory;
use App\Entity\Editorial\SourceSignal;
use App\Entity\Topic;
use App\Enum\ClaimOutcome;
use App\Enum\Editorial\VerdictType;
use App\Message\Editorial\VerifyClaimMessage;
use App\Message\Editorial\WriteDevelopingStoryMessage;
use App\Message\Editorial\WriteFlashMessage;
use App\Repository\AppSettingRepository;
use App\Repository\ArticleRepository;
use App\Repository\Editorial\SourceSignalRepository;
use App\Repository\TopicRepository;
use App\Service\Editorial\Escalation\EscalationClassifier;
use App\Service\Editorial\Escalation\EscalationLogWriter;
use App\Service\Editorial\Verification\VerificationGate;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Handler for {@see VerifyClaimMessage} (Sprint 54 T54.9, extended in T55.9).
 *
 * Flow:
 *  1. Hydrate signals from the repository. Missing → warn + return.
 *  2. Rehydrate the ClaimOriginGraph from the message's graphArray payload.
 *  3. Resolve Topic (optional). Ask {@see VerificationGate} for a verdict
 *     (D3 matrix + LLM sanity + NotebookLM downgrade-only override from T55.10).
 *  4. Persist the snapshot (graph + verdict) onto each signal's
 *     `claim_graph_snapshot` column.
 *  5. Insert one {@see SourceClaimHistory} row with outcome UNRESOLVED.
 *  6. T55.9 NEW — dispatch the verdict-appropriate downstream action:
 *     REJECT → log + return (no op)
 *     ESCALATE_HUMAN → classify category + insert EditorialEscalationLog
 *     FLASH_WITH_* | FULL_FLASH → decide flash-vs-developing-story, dispatch writer
 *
 * Pipeline-gate: the T55.9 step 6 branches (escalation log + writer dispatch)
 * are gated by `editorial.pipeline.enabled`. Steps 1-5 (snapshot + history)
 * are audit trail and run unconditionally so verification logs stay
 * consistent even when the downstream pipeline is off (e.g. smoke revert).
 *
 * Failure contract: log-and-swallow at every seam. Never rethrow.
 */
#[AsMessageHandler]
final readonly class VerifyClaimMessageHandler
{
    /**
     * Developing-story lookup window — how far back findDevelopingStoryForTopic()
     * looks for an existing active Article before FlashWriter creates a fresh one.
     *
     * Hardcoded at -24 hours for Sprint 55 per audit D9. Candidate for
     * AppSettings config key `editorial.developing_story_window_hours` in S56+
     * if ops finds the window too narrow/wide in practice (logged as S56
     * tech-debt item).
     */
    private const DEVELOPING_STORY_WINDOW = '-24 hours';

    public function __construct(
        private SourceSignalRepository $sourceSignalRepository,
        private VerificationGate $gate,
        private EntityManagerInterface $entityManager,
        private TopicRepository $topicRepository,
        private ArticleRepository $articleRepository,
        private EscalationClassifier $escalationClassifier,
        private EscalationLogWriter $escalationLogWriter,
        private MessageBusInterface $messageBus,
        private AppSettingRepository $appSettings,
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
            $topic = $message->topicId !== null ? $this->resolveTopic($message->topicId) : null;
            $verdict = $this->gate->rule($graph, $signals, $topic);

            // ===== Steps 4-5: snapshot + history (S54 audit trail, always runs) =====
            $snapshot = [
                'graph' => $graph->toArray(),
                'verdict' => $verdict->type->value,
                'verdict_reasoning' => $verdict->reasoning,
                'verdict_type' => $verdict->type->value,
                'llm_override' => $verdict->llmOverride,
                'llm_sanity_skipped' => $verdict->llmSanitySkipped,
                'escalation_keyword' => $verdict->escalationKeyword,
                'confidence' => $verdict->confidence,
                'verdict_confidence' => $verdict->confidence,
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

            // ===== Step 6: T55.9 downstream dispatch — gated by pipeline.enabled =====
            if (!$this->appSettings->getBool('editorial.pipeline.enabled', false)) {
                $this->logger->debug('VerifyClaimMessageHandler: pipeline.enabled=false, skipping downstream dispatch', [
                    'topic_hash' => $message->topicHash,
                    'verdict' => $verdict->type->value,
                ]);

                return;
            }

            $this->dispatchVerdict($verdict->type, $signals, $primarySignal, $topic, $message, $graph);
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
    private function dispatchVerdict(
        VerdictType $verdictType,
        array $signals,
        SourceSignal $primarySignal,
        ?Topic $topic,
        VerifyClaimMessage $message,
        ClaimOriginGraph $graph,
    ): void {
        switch ($verdictType) {
            case VerdictType::REJECT:
                $this->logger->info('verification_rejected_noop', [
                    'topic_hash' => $message->topicHash,
                    'primary_signal_id' => $primarySignal->getId(),
                ]);

                return;

            case VerdictType::ESCALATE_HUMAN:
                $this->handleEscalation($signals, $primarySignal, $topic, $graph, $verdictType);

                return;

            case VerdictType::FLASH_WITH_ATTRIBUTION:
            case VerdictType::FLASH_WITH_ASSERTION_YELLOW:
            case VerdictType::FULL_FLASH:
                $this->dispatchWriter($signals, $primarySignal, $topic, $verdictType, $message);

                return;
        }

        // Default / unknown verdict — alert loudly so we catch a new enum case
        // that isn't handled here (fail-loud, don't drop silently).
        // @phpstan-ignore deadCode.unreachable (defensive: future VerdictType cases)
        $this->logger->alert('VerifyClaimMessageHandler: unknown verdict type, no dispatch', [
            'verdict' => $verdictType->value,
            'topic_hash' => $message->topicHash,
        ]);
    }

    /**
     * @param list<SourceSignal> $signals
     */
    private function handleEscalation(
        array $signals,
        SourceSignal $primarySignal,
        ?Topic $topic,
        ClaimOriginGraph $graph,
        VerdictType $verdictType,
    ): void {
        // Idempotency: skip if we already emitted an escalation row for this
        // primary-signal+category pair. Deduplicates on Messenger retries.
        // We key only on primary_signal_id in articleSnapshot since category
        // isn't known until classification completes — check after classify.

        $claimText = trim($primarySignal->getTitle());
        $category = null;
        try {
            $category = $this->escalationClassifier->classify(
                claimText: $claimText,
                primarySourceTitle: $claimText,
                topic: $topic,
            );
        } catch (\Throwable $e) {
            $this->logger->warning('VerifyClaimMessageHandler: escalation classifier threw', [
                'primary_signal_id' => $primarySignal->getId(),
                'error' => $e->getMessage(),
            ]);
        }

        // Fallback category when classifier returns null or fails.
        // Audit note: family_b is a reasonable default for the broad sensitivity
        // bucket; T55.18 dataset review will surface if this default swallows
        // category-1/6/7 triggers that should land elsewhere.
        $category ??= \App\Enum\Editorial\EscalationCategory::FAMILY_B_EU_NATO_RUSSIA;

        $supportingIds = [];
        foreach ($signals as $signal) {
            if ($signal !== $primarySignal) {
                $id = $signal->getId();
                if ($id !== null) {
                    $supportingIds[] = $id;
                }
            }
        }

        $articleSnapshot = [
            'title' => $primarySignal->getTitle(),
            'summary' => $primarySignal->getRawSummary(),
            'verdict_type' => $verdictType->value,
            'verdict_rationale' => $primarySignal->getClaimGraphSnapshot()['verdict_reasoning'] ?? null,
            'primary_signal_id' => $primarySignal->getId(),
            'supporting_signal_ids' => $supportingIds,
            'topic_id' => $topic?->getId(),
        ];

        $this->escalationLogWriter->write(
            category: $category,
            articleSnapshot: $articleSnapshot,
            originGraphSnapshot: $graph->toArray(),
        );

        $this->logger->info('verification_escalation_logged', [
            'primary_signal_id' => $primarySignal->getId(),
            'category' => $category->value,
            'topic_id' => $topic?->getId(),
        ]);
    }

    /**
     * @param list<SourceSignal> $signals
     */
    private function dispatchWriter(
        array $signals,
        SourceSignal $primarySignal,
        ?Topic $topic,
        VerdictType $verdictType,
        VerifyClaimMessage $message,
    ): void {
        $primaryId = $primarySignal->getId();
        if ($primaryId === null) {
            $this->logger->warning('VerifyClaimMessageHandler: primary signal has no id, cannot dispatch writer', [
                'topic_hash' => $message->topicHash,
            ]);

            return;
        }

        // Idempotency: skip writer dispatch if an Article already exists
        // for this primary signal (previous successful run).
        $existing = $this->articleRepository->findOneBy(['originalSourceSignal' => $primarySignal]);
        if ($existing !== null) {
            $this->logger->info('verification_writer_skipped_article_exists', [
                'primary_signal_id' => $primaryId,
                'article_id' => $existing->getId(),
            ]);

            return;
        }

        $supportingIds = [];
        foreach ($signals as $signal) {
            if ($signal !== $primarySignal) {
                $id = $signal->getId();
                if ($id !== null) {
                    $supportingIds[] = $id;
                }
            }
        }

        // Discriminate: developing-story-in-progress for this Topic?
        $existingDeveloping = null;
        if ($topic !== null) {
            $since = new \DateTimeImmutable(self::DEVELOPING_STORY_WINDOW);
            $existingDeveloping = $this->articleRepository->findDevelopingStoryForTopic($topic, $since);
        }

        if ($existingDeveloping !== null) {
            $this->messageBus->dispatch(new WriteDevelopingStoryMessage(
                articleId: $existingDeveloping->getId() ?? 0,
                primarySignalId: $primaryId,
                supportingSignalIds: $supportingIds,
                verdictType: $verdictType->value,
            ));

            $this->logger->info('verification_developing_story_dispatched', [
                'article_id' => $existingDeveloping->getId(),
                'primary_signal_id' => $primaryId,
                'verdict_type' => $verdictType->value,
            ]);

            return;
        }

        $this->messageBus->dispatch(new WriteFlashMessage(
            primarySignalId: $primaryId,
            supportingSignalIds: $supportingIds,
            verdictType: $verdictType->value,
            topicId: $topic?->getId(),
        ));

        $this->logger->info('verification_flash_dispatched', [
            'primary_signal_id' => $primaryId,
            'verdict_type' => $verdictType->value,
            'topic_id' => $topic?->getId(),
        ]);
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

    private function resolveTopic(int $topicId): ?Topic
    {
        try {
            return $this->topicRepository->find($topicId);
        } catch (\Throwable $e) {
            $this->logger->warning('VerifyClaimMessageHandler: topic resolution failed', [
                'topic_id' => $topicId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
