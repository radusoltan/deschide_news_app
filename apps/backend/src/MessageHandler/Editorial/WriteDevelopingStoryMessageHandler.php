<?php

declare(strict_types=1);

namespace App\MessageHandler\Editorial;

use App\Dto\Editorial\VerificationVerdict;
use App\Entity\Article;
use App\Entity\Editorial\SourceSignal;
use App\Enum\ArticleStatus;
use App\Enum\Editorial\VerdictType;
use App\Message\Editorial\WriteDevelopingStoryMessage;
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
        private readonly LoggerInterface $logger,
    ) {}

    public function __invoke(WriteDevelopingStoryMessage $message): ?Article
    {
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

        return $this->applyGuardAndRetranslate($updated, $primary, $supporting, $verdict);
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
