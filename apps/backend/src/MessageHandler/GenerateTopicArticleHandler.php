<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\PressRelease;
use App\Entity\Topic;
use App\Enum\PressReleaseStatus;
use App\Enum\SourceType;
use App\Message\GenerateTopicArticleMessage;
use App\Repository\AppSettingRepository;
use App\Repository\PressReleaseRepository;
use App\Service\Editorial\ArticleWriterService;
use App\Service\Editorial\WriterThrottle;
use App\Service\Verification\SemanticVerifierService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * Async wrapper around the synchronous topic-window article generation
 * flow that GenerateArticleCommand runs in T52.6 sync mode.
 *
 * Mirrors the command's per-topic sync logic exactly (ADR-019 D2):
 * fetch topic, fetch PRs, eligibility check, optional semantic dedup,
 * call writer, persist draft as PressRelease (status=PENDING).
 *
 * Handler entry ordering aligns with ADR-022 D5 + D2 (symmetric with
 * WriteFlashMessageHandler / WriteDevelopingStoryMessageHandler):
 *   emergency_halt (silent ack) → writer throttle (re-enqueue) → work.
 * GenerateTopicArticleMessage carries no `approvedEscalationLogId`
 * field, so the escalation bypass from the sibling L3 handlers does
 * not apply here — every dispatch is subject to the throttle.
 *
 * Per-topic failures (topic not found / inactive, ineligibility, writer
 * null, dedup skip) are logged and the handler returns gracefully — the
 * Messenger transport's retry policy applies only to thrown exceptions.
 */
#[AsMessageHandler]
final readonly class GenerateTopicArticleHandler
{
    /** Threshold above which a topic is considered too noisy (>50% PRs in duplicate pairs). */
    private const DUPLICATE_RATIO_SKIP_THRESHOLD = 0.5;

    public function __construct(
        private EntityManagerInterface $em,
        private PressReleaseRepository $pressReleaseRepository,
        private ArticleWriterService $writerService,
        private SemanticVerifierService $semanticVerifier,
        private AppSettingRepository $appSettings,
        private WriterThrottle $writerThrottle,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(GenerateTopicArticleMessage $message): void
    {
        // Emergency circuit breaker (ADR-022 D5). Short-circuits before
        // any LLM work so mid-run halts work even for in-flight messages.
        if ($this->appSettings->getBool('editorial.emergency_halt', false)) {
            $this->logger->info('emergency_halt.triggered', [
                'handler' => self::class,
                'message_class' => $message::class,
                'topicId' => $message->topicId,
            ]);

            return;
        }

        // Writer throttle (ADR-022 D2). No escalation bypass here —
        // GenerateTopicArticleMessage is always subject to the limit.
        $rateLimit = $this->writerThrottle->consume();
        if (!$rateLimit->isAccepted()) {
            $retryAfterMs = $this->computeRetryAfterMs($rateLimit->getRetryAfter());
            $this->logger->info('throttle.blocked', [
                'handler' => self::class,
                'message_class' => $message::class,
                'topicId' => $message->topicId,
                'retry_after_ms' => $retryAfterMs,
                'limit' => $rateLimit->getLimit(),
            ]);

            $this->messageBus->dispatch($message, [new DelayStamp($retryAfterMs)]);

            return;
        }

        $topic = $this->em->find(Topic::class, $message->topicId);

        if ($topic === null) {
            $this->logger->warning('GenerateTopicArticle: topic not found', [
                'topicId' => $message->topicId,
            ]);

            return;
        }

        if (!$topic->isActive()) {
            $this->logger->info('GenerateTopicArticle: topic is inactive, skipping', [
                'topicId' => $message->topicId,
            ]);

            return;
        }

        $pressReleases = $this->pressReleaseRepository->findByTopicInWindow(
            $topic,
            $message->windowStart,
            $message->windowEnd,
        );

        if ($pressReleases === []) {
            $this->logger->info('GenerateTopicArticle: no press releases in window', [
                'topicId' => $message->topicId,
                'windowStart' => $message->windowStart->format(\DateTimeInterface::ATOM),
                'windowEnd' => $message->windowEnd->format(\DateTimeInterface::ATOM),
            ]);

            return;
        }

        $eligibility = $this->writerService->isEligibleForTopicWindow($topic, $pressReleases);
        if (!$eligibility['eligible']) {
            $this->logger->info('GenerateTopicArticle: topic ineligible', [
                'topicId' => $message->topicId,
                'reasons' => $eligibility['reasons'],
                'prCount' => $eligibility['prCount'],
                'avgConfidence' => $eligibility['avgConfidence'],
                'avgContentLength' => $eligibility['avgContentLength'],
            ]);

            return;
        }

        // Semantic dedup gate (ADR-019 D3): skip if >50% of PRs are involved
        // in duplicate pairs. Fail-open by service contract — verifier
        // returning [] (whether truly no dups or LLM error) means no skip.
        if ($this->shouldSkipForDuplicates($topic, $pressReleases, $message)) {
            return;
        }

        $draft = $this->writerService->writeArticleFromTopicWindow(
            $topic,
            $message->windowStart,
            $message->windowEnd,
            $pressReleases,
        );

        if ($draft === null) {
            $this->logger->warning('GenerateTopicArticle: writer returned null (Gemini error or invalid output)', [
                'topicId' => $message->topicId,
            ]);

            return;
        }

        $pr = new PressRelease();
        $pr->setTitle(mb_substr($draft->titleRo, 0, 255));
        $pr->setLead($draft->leadRo);
        $pr->setContent($draft->contentRo);
        $pr->setStatus(PressReleaseStatus::PENDING);
        $pr->setSourceType(SourceType::AGGREGATOR);
        $pr->setSourceName(sprintf('AI:topic#%d', $topic->getId() ?? 0));
        $pr->setContentHash(hash('sha256', sprintf('ai_gen_topic_%d_%d', $topic->getId() ?? 0, time())));
        $pr->setCategorySlug('externe');
        $pr->setDetectedLanguage('ro');
        $pr->setAiConfidenceScore($draft->confidenceScore);
        $pr->setAiSourceCount(\count($draft->sourcePressReleaseIds));

        $this->em->persist($pr);
        $this->em->flush();

        $this->logger->info('GenerateTopicArticle: PressRelease created (pending review)', [
            'topicId' => $message->topicId,
            'pressReleaseId' => $pr->getId(),
            'confidence' => $draft->confidenceScore,
            'sourceCount' => \count($draft->sourcePressReleaseIds),
        ]);
    }

    /**
     * @param PressRelease[] $pressReleases
     */
    private function shouldSkipForDuplicates(Topic $topic, array $pressReleases, GenerateTopicArticleMessage $message): bool
    {
        $windowHours = max(
            1,
            (int) (($message->windowEnd->getTimestamp() - $message->windowStart->getTimestamp()) / 3600),
        );

        $pairs = $this->semanticVerifier->findDuplicatePairsInTopicWindow(
            $topic,
            $pressReleases,
            $windowHours,
        );

        if ($pairs === []) {
            return false;
        }

        $idsInPairs = [];
        foreach ($pairs as $pair) {
            $idsInPairs[$pair[0]] = true;
            $idsInPairs[$pair[1]] = true;
        }
        $duplicateRatio = \count($idsInPairs) / \count($pressReleases);

        $this->logger->info('GenerateTopicArticle: semantic dedup result', [
            'topicId' => $topic->getId(),
            'pairCount' => \count($pairs),
            'prsInPairs' => \count($idsInPairs),
            'totalPrs' => \count($pressReleases),
            'duplicateRatio' => round($duplicateRatio, 2),
        ]);

        if ($duplicateRatio > self::DUPLICATE_RATIO_SKIP_THRESHOLD) {
            $this->logger->warning('GenerateTopicArticle: skipping topic — duplicate ratio exceeds threshold', [
                'topicId' => $topic->getId(),
                'duplicateRatio' => round($duplicateRatio, 2),
                'threshold' => self::DUPLICATE_RATIO_SKIP_THRESHOLD,
            ]);

            return true;
        }

        return false;
    }

    /**
     * Mirrors WriteDevelopingStoryMessageHandler::computeRetryAfterMs()
     * — convert the RateLimiter retry-after moment into a bounded
     * Messenger DelayStamp in milliseconds.
     */
    private function computeRetryAfterMs(\DateTimeImmutable $retryAfter): int
    {
        $deltaSeconds = max(1, $retryAfter->getTimestamp() - time());

        return min(3_600_000, $deltaSeconds * 1000);
    }
}
