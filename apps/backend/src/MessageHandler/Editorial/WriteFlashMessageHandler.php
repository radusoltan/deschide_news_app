<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Dto\Editorial\VerificationVerdict;
use App\Entity\Article;
use App\Entity\Editorial\SourceSignal;
use App\Enum\ArticleStatus;
use App\Enum\Editorial\VerdictType;
use App\Message\Editorial\WriteFlashMessage;
use App\Repository\AppSettingRepository;
use App\Repository\ArticleRepository;
use App\Repository\Editorial\SourceSignalRepository;
use App\Repository\TopicRepository;
use App\Service\Editorial\Escalation\EscalationLogWriter;
use App\Service\Editorial\Guard\GuardEscalationCategoryMapper;
use App\Service\Editorial\Guard\GuardPipelineInterface;
use App\Service\Editorial\PostApprovalDispatcher;
use App\Service\Editorial\Writer\FlashWriter;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Async handler for {@see WriteFlashMessage} (Sprint 55 T55.3, extended in T55.9)
 * running on the `editorial_flash` transport.
 *
 * Orchestrates the full writer → guard → publish flow:
 *   1. Resolve SourceSignal cluster + Topic from ids (defensive no-op if
 *      primary signal missing or verdict type unknown).
 *   2. Idempotency guard: if an Article already exists for this primary
 *      signal, return it without redoing the LLM work. Prevents duplicate
 *      Articles on Messenger retry storms.
 *   3. Delegate to {@see FlashWriter::write()} for the actual LLM + persist.
 *      Writer emits Article with `publishedLocales=[]` (invisible to public
 *      feed) and does NOT dispatch translations/ingestion — that's this
 *      handler's job, post-Guard.
 *   4. Run the L4 guard pipeline.
 *   5. Decide based on {@see GuardVerdict}:
 *      - `passed=true`                         → set publishedLocales=['ro'],
 *        flush, dispatch PostApprovalDispatcher (translate + ingest).
 *      - `passed=false + escalationCode=null`  → append guard_flag to
 *        internalSummary, leave publishedLocales empty, flush. Article
 *        stays invisible + status=NEW for manual editor review.
 *      - `passed=false + escalationCode=X`     → status=ARCHIVED, flush,
 *        insert EditorialEscalationLog row via LogWriter. Article is
 *        permanently removed from any public surface.
 *
 * Failure contract: logged + no-op, never rethrow; matches the Sprint 53+
 * pattern so Messenger does not retry-storm on transient errors.
 */
#[AsMessageHandler]
class WriteFlashMessageHandler
{
    public function __construct(
        private readonly FlashWriter $flashWriter,
        private readonly GuardPipelineInterface $guardPipeline,
        private readonly PostApprovalDispatcher $postApprovalDispatcher,
        private readonly EscalationLogWriter $escalationLogWriter,
        private readonly GuardEscalationCategoryMapper $categoryMapper,
        private readonly SourceSignalRepository $signalRepository,
        private readonly ArticleRepository $articleRepository,
        private readonly TopicRepository $topicRepository,
        private readonly EntityManagerInterface $em,
        private readonly AppSettingRepository $appSettings,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(WriteFlashMessage $message): ?Article
    {
        // Emergency circuit breaker (T56.02, ADR-022 D5). Short-circuits
        // BEFORE any LLM call or guard invocation so mid-run halts work
        // even with in-flight messages already dispatched to the queue.
        if ($this->appSettings->getBool('editorial.emergency_halt', false)) {
            $this->logger->info('emergency_halt.triggered', [
                'handler' => self::class,
                'message_class' => $message::class,
                'message_id_hint' => $message->primarySignalId,
            ]);

            return null;
        }

        try {
            $primary = $this->signalRepository->find($message->primarySignalId);
            if ($primary === null) {
                $this->logger->warning('write_flash_primary_signal_missing', [
                    'primary_signal_id' => $message->primarySignalId,
                ]);

                return null;
            }

            // Idempotency: if we already persisted an Article for this primary
            // signal (previous successful run of the same message), return it
            // without regenerating. Messenger retries on transient failures
            // (DB deadlock, LLM timeout) must not produce duplicate Articles.
            $existing = $this->articleRepository->findOneBy(['originalSourceSignal' => $primary]);
            if ($existing !== null) {
                $this->logger->info('write_flash_idempotent_short_circuit', [
                    'primary_signal_id' => $message->primarySignalId,
                    'article_id' => $existing->getId(),
                ]);

                return $existing;
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

            $verdict = new VerificationVerdict(
                type: $verdictType,
                reasoning: 'dispatched-from-verify-claim-handler',
                confidence: $this->extractConfidence($primary),
            );

            $article = $this->flashWriter->write($primary, $supporting, $verdict, $topic);

            // T56.05 — editor approve-from-escalation bypass. When the
            // controller sets approvedEscalationLogId, the Guard chain MUST
            // be skipped: the editor has already adjudicated the very
            // failures that produced the original EditorialEscalationLog,
            // so re-running GuardPipeline->check() here would re-escalate
            // on the same signals and silently cancel the override.
            if ($message->approvedEscalationLogId !== null) {
                return $this->publishWithEscalationBypass($article, $message->approvedEscalationLogId);
            }

            return $this->applyGuardAndPublish($article, $primary, $supporting, $verdict);
        } catch (\Throwable $e) {
            // Contract: log + no-op, never rethrow. Matches VerifyClaimMessageHandler
            // pattern so a transient writer/guard fault does not spiral into a
            // Messenger retry storm — the idempotency short-circuit on the next
            // run would otherwise pin the Article in NEW/publishedLocales=[]
            // forever.
            $this->logger->error('write_flash_handler_failed', [
                'primary_signal_id' => $message->primarySignalId,
                'exception' => $e::class,
                'error' => $e->getMessage(),
                'trace' => mb_substr($e->getTraceAsString(), 0, 500),
            ]);

            return null;
        }
    }

    /**
     * T56.05 — publish the Article without running the Guard chain. Used
     * only from the editor approve-from-escalation path (see
     * {@see \App\Controller\Admin\AdminEscalationController::approve()}).
     *
     * Side-effects mirror the guard-pass branch of {@see applyGuardAndPublish()}:
     * flip `publishedLocales=['ro']`, flush, fan out via PostApprovalDispatcher.
     * The log entry is separated (`write_flash_published.bypass`) so
     * analytics can distinguish editor-overrides from guard-passed Articles.
     */
    private function publishWithEscalationBypass(Article $article, int $approvedEscalationLogId): Article
    {
        $article->setPublishedLocales(['ro']);
        $this->em->flush();

        $this->postApprovalDispatcher->dispatch($article, null, 'ro');

        $this->logger->info('write_flash_published.bypass', [
            'article_id' => $article->getId(),
            'escalation_log_id' => $approvedEscalationLogId,
        ]);

        return $article;
    }

    /**
     * @param list<SourceSignal> $supporting
     */
    private function applyGuardAndPublish(
        Article $article,
        SourceSignal $primary,
        array $supporting,
        VerificationVerdict $verdict,
    ): Article {
        $guardVerdict = $this->guardPipeline->check($article, [
            'verdict_type' => $verdict->type->value,
            'verdict_confidence' => $verdict->confidence,
        ]);

        if ($guardVerdict->passed) {
            // Only now does the Article become publicly visible. FlashWriter
            // set publishedLocales=[] so it was hidden until this flip.
            $article->setPublishedLocales(['ro']);
            $this->em->flush();

            $this->postApprovalDispatcher->dispatch($article, null, 'ro');

            $this->logger->info('write_flash_published', [
                'article_id' => $article->getId(),
                'warnings' => $guardVerdict->warnings,
            ]);

            return $article;
        }

        if ($guardVerdict->escalationCode !== null) {
            $article->setStatus(ArticleStatus::ARCHIVED);
            $this->em->flush();

            $category = $this->categoryMapper->map($guardVerdict->escalationCode);
            $this->escalationLogWriter->write(
                category: $category,
                articleSnapshot: $this->buildEscalationSnapshot($article, $primary, $supporting, $verdict),
                originGraphSnapshot: $this->extractOriginGraphSnapshot($primary),
            );

            $this->logger->warning('write_flash_guard_escalated', [
                'article_id' => $article->getId(),
                'escalation_code' => $guardVerdict->escalationCode,
                'category' => $category->value,
                'failures' => $guardVerdict->failures,
            ]);

            return $article;
        }

        // Non-escalating failure — leave Article in NEW + invisible state,
        // annotate internalSummary for the editor. publishedLocales stays
        // empty so it does NOT appear on public feeds.
        $flaggedSummary = sprintf(
            '%s [guard_flag: %s]',
            $article->getInternalSummary() ?? '',
            implode('; ', $guardVerdict->failures),
        );
        $article->setInternalSummary(trim($flaggedSummary));
        $this->em->flush();

        $this->logger->warning('write_flash_guard_flagged_for_editor', [
            'article_id' => $article->getId(),
            'failures' => $guardVerdict->failures,
            'warnings' => $guardVerdict->warnings,
        ]);

        return $article;
    }

    /**
     * @param list<SourceSignal> $supporting
     * @return array<string, mixed>
     */
    private function buildEscalationSnapshot(
        Article $article,
        SourceSignal $primary,
        array $supporting,
        VerificationVerdict $verdict,
    ): array {
        return [
            'article_id' => $article->getId(),
            'title' => $article->getTitle(),
            'lead' => $article->getLead(),
            'content' => $article->getContent(),
            'verdict_type' => $verdict->type->value,
            'verdict_rationale' => $verdict->reasoning,
            'primary_signal_id' => $primary->getId(),
            'supporting_signal_ids' => array_values(array_filter(
                array_map(static fn (SourceSignal $s): ?int => $s->getId(), $supporting),
            )),
            'topic_id' => $article->getTopics()->first() !== false
                ? $article->getTopics()->first()->getId()
                : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractOriginGraphSnapshot(SourceSignal $primary): array
    {
        $snapshot = $primary->getClaimGraphSnapshot();
        if (!\is_array($snapshot)) {
            return [];
        }
        if (isset($snapshot['graph']) && \is_array($snapshot['graph'])) {
            return $snapshot['graph'];
        }

        return $snapshot;
    }

    private function extractConfidence(SourceSignal $signal): float
    {
        $snapshot = $signal->getClaimGraphSnapshot();
        if (!\is_array($snapshot)) {
            return 1.0;
        }
        $confidence = $snapshot['verdict_confidence'] ?? $snapshot['confidence'] ?? null;

        return \is_numeric($confidence) ? (float) $confidence : 1.0;
    }
}
