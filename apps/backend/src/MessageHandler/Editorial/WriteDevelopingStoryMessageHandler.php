<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Dto\Editorial\VerificationVerdict;
use App\Entity\Article;
use App\Entity\Editorial\SourceSignal;
use App\Enum\ArticleStatus;
use App\Enum\Editorial\VerdictType;
use App\Message\Editorial\WriteDevelopingStoryMessage;
use App\Repository\AppSettingRepository;
use App\Repository\ArticleRepository;
use App\Repository\Editorial\SourceSignalRepository;
use App\Service\Editorial\Escalation\EscalationLogWriter;
use App\Service\Editorial\Guard\GuardEscalationCategoryMapper;
use App\Service\Editorial\Guard\GuardPipelineInterface;
use App\Service\Editorial\Writer\DevelopingStoryWriter;
use App\Service\TranslationPriorityDispatcher;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Async handler for {@see WriteDevelopingStoryMessage} (Sprint 55 T55.4,
 * extended in T55.9) running on the `editorial_flash` transport.
 *
 * Orchestrates: writer in-place revision → guard → re-translation dispatch.
 *
 * Guard outcomes mirror {@see WriteFlashMessageHandler} but with dispatch
 * differences:
 *   - pass   → dispatch TranslationPriorityDispatcher (translate-only,
 *     NOT IngestArticleMessage — developing stories are not re-ingested
 *     through the one-shot post-creation pipeline)
 *   - escalate (Legal Cat6 etc.) → ARCHIVE the Article (end of its life
 *     cycle for the developing story) + insert EditorialEscalationLog
 *   - flag   → append guard_flag to internalSummary, NO re-translation
 *     (new content stays in RO only until editor reviews)
 */
#[AsMessageHandler]
class WriteDevelopingStoryMessageHandler
{
    public function __construct(
        private readonly DevelopingStoryWriter $developingStoryWriter,
        private readonly GuardPipelineInterface $guardPipeline,
        private readonly TranslationPriorityDispatcher $translationDispatcher,
        private readonly EscalationLogWriter $escalationLogWriter,
        private readonly GuardEscalationCategoryMapper $categoryMapper,
        private readonly ArticleRepository $articleRepository,
        private readonly SourceSignalRepository $signalRepository,
        private readonly EntityManagerInterface $em,
        private readonly AppSettingRepository $appSettings,
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(WriteDevelopingStoryMessage $message): ?Article
    {
        // Emergency circuit breaker (T56.02, ADR-022 D5). Short-circuits
        // BEFORE any LLM call or guard invocation so mid-run halts work
        // even with in-flight messages already dispatched to the queue.
        if ($this->appSettings->getBool('editorial.emergency_halt', false)) {
            $this->logger->info('emergency_halt.triggered', [
                'handler' => self::class,
                'message_class' => $message::class,
                'message_id_hint' => $message->articleId,
            ]);

            return null;
        }

        try {
            $article = $this->articleRepository->find($message->articleId);
            if ($article === null) {
                $this->logger->warning('write_developing_article_missing', [
                    'article_id' => $message->articleId,
                ]);

                return null;
            }

            $primary = $this->signalRepository->find($message->primarySignalId);
            if ($primary === null) {
                $this->logger->warning('write_developing_primary_signal_missing', [
                    'article_id' => $message->articleId,
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
                $this->logger->error('write_developing_unknown_verdict_type', [
                    'verdict_type' => $message->verdictType,
                    'article_id' => $message->articleId,
                ]);

                return null;
            }

            $verdict = new VerificationVerdict(
                type: $verdictType,
                reasoning: 'dispatched-from-verify-claim-handler',
                confidence: $this->extractConfidence($primary),
            );

            $updated = $this->developingStoryWriter->write($article, $primary, $supporting, $verdict);
            if ($updated === null) {
                // Writer rejected (wrong type / archived). Handler logs already fired inside.
                return null;
            }

            // T56.05 — editor approve-from-escalation bypass (symmetric with
            // WriteFlashMessageHandler). Skip the Guard chain when the
            // controller has marked this dispatch as editor-approved, else
            // the same failures that triggered the original escalation would
            // bounce us back into guard_flag / guard_escalated paths.
            if ($message->approvedEscalationLogId !== null) {
                return $this->retranslateWithEscalationBypass($updated, $message->approvedEscalationLogId);
            }

            return $this->applyGuardAndRetranslate($updated, $primary, $supporting, $verdict);
        } catch (\Throwable $e) {
            // Contract: log + no-op, never rethrow. Matches VerifyClaimMessageHandler
            // pattern — retry storms would keep reopening a live Article for
            // repeated partial revision attempts, desyncing translations.
            $this->logger->error('write_developing_handler_failed', [
                'article_id' => $message->articleId,
                'primary_signal_id' => $message->primarySignalId,
                'exception' => $e::class,
                'error' => $e->getMessage(),
                'trace' => mb_substr($e->getTraceAsString(), 0, 500),
            ]);

            return null;
        }
    }

    /**
     * T56.05 — re-translate the revised Article without running the Guard
     * chain. Symmetric with {@see WriteFlashMessageHandler::publishWithEscalationBypass()}.
     *
     * Mirrors the guard-pass branch of {@see applyGuardAndRetranslate()}:
     * dispatch a forced re-translation for EN+RU. No publishedLocales flip
     * here — a developing-story Article is already public; only its body
     * has been revised by the writer.
     */
    private function retranslateWithEscalationBypass(Article $article, int $approvedEscalationLogId): Article
    {
        $this->translationDispatcher->dispatch(
            article: $article,
            locales: ['ru', 'en'],
            forceRetranslate: true,
        );

        $this->logger->info('write_developing_published.bypass', [
            'article_id' => $article->getId(),
            'revision' => $article->getRevisionCount(),
            'escalation_log_id' => $approvedEscalationLogId,
        ]);

        return $article;
    }

    /**
     * @param list<SourceSignal> $supporting
     */
    private function applyGuardAndRetranslate(
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
            $this->translationDispatcher->dispatch(
                article: $article,
                locales: ['ru', 'en'],
                forceRetranslate: true,
            );

            $this->logger->info('write_developing_published', [
                'article_id' => $article->getId(),
                'revision' => $article->getRevisionCount(),
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

            $this->logger->warning('write_developing_guard_escalated', [
                'article_id' => $article->getId(),
                'escalation_code' => $guardVerdict->escalationCode,
                'category' => $category->value,
                'failures' => $guardVerdict->failures,
            ]);

            return $article;
        }

        $flaggedSummary = sprintf(
            '%s [guard_flag: %s]',
            $article->getInternalSummary() ?? '',
            implode('; ', $guardVerdict->failures),
        );
        $article->setInternalSummary(trim($flaggedSummary));
        $this->em->flush();

        $this->logger->warning('write_developing_guard_flagged_for_editor', [
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
            'revision_count' => $article->getRevisionCount(),
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
