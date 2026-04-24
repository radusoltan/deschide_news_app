<?php

declare(strict_types=1);

namespace App\MessageHandler\Topic;

use App\Entity\Topic;
use App\Enum\BriefingCadence;
use App\Enum\BriefingStatus;
use App\Message\GenerateTopicArticleMessage;
use App\Message\Topic\GenerateTopicBriefingMessage;
use App\Repository\AppSettingRepository;
use App\Service\Editorial\TopicBriefingWriterService;
use App\ValueObject\DateRange;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Per-topic briefing generation handler.
 *
 * Receives dispatched messages from TriggerTopicBriefingRunHandler
 * and delegates to TopicBriefingWriterService for the dual-LLM chain.
 *
 * On successful briefing (status != FAILED), chains an article
 * proposal dispatch: HOURLY + DAILY cadences trigger
 * GenerateTopicArticleMessage on the same topic + window, so the
 * AI-proposed PressRelease lands in the editorial queue alongside
 * the briefing. Kill-switch via
 * `article_generation.auto_from_briefing` AppSetting (default true).
 * WEEKLY briefings do NOT chain — weekly = retrospective summary,
 * not breaking-news trigger.
 */
#[AsMessageHandler]
final readonly class GenerateTopicBriefingHandler
{
    public function __construct(
        private TopicBriefingWriterService $writerService,
        private EntityManagerInterface $em,
        private AppSettingRepository $appSettings,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(GenerateTopicBriefingMessage $message): void
    {
        $topic = $this->em->find(Topic::class, $message->topicId);

        if ($topic === null) {
            $this->logger->warning('GenerateTopicBriefing: topic not found', [
                'topicId' => $message->topicId,
            ]);

            return;
        }

        $range = new DateRange(
            new \DateTimeImmutable($message->periodFrom),
            new \DateTimeImmutable($message->periodTo),
        );

        $this->logger->info('GenerateTopicBriefing: generating for topic', [
            'topicId' => $message->topicId,
            'cadence' => $message->cadence->value,
            'periodFrom' => $message->periodFrom,
            'periodTo' => $message->periodTo,
        ]);

        $briefing = $this->writerService->generate($topic, $message->cadence, $range);

        if ($briefing === null) {
            $this->logger->info('GenerateTopicBriefing: no briefing generated (no data or LLM failure)', [
                'topicId' => $message->topicId,
                'cadence' => $message->cadence->value,
            ]);

            return;
        }

        $this->logger->info('GenerateTopicBriefing: briefing created', [
            'briefingId' => $briefing->getId(),
            'topicId' => $message->topicId,
            'cadence' => $message->cadence->value,
            'status' => $briefing->getStatus()->value,
            'claudePolished' => $briefing->isClaudePolished(),
        ]);

        $this->maybeDispatchArticleProposal($topic, $briefing->getStatus(), $message->cadence, $range);
    }

    /**
     * Chain an AI article proposal dispatch when the briefing has usable
     * signal (status != FAILED) and cadence is HOURLY or DAILY. WEEKLY
     * is excluded — retrospective, not breaking. Kill-switch via
     * `article_generation.auto_from_briefing` AppSetting.
     */
    private function maybeDispatchArticleProposal(
        Topic $topic,
        BriefingStatus $briefingStatus,
        BriefingCadence $cadence,
        DateRange $range,
    ): void {
        if ($briefingStatus === BriefingStatus::FAILED) {
            return;
        }

        if (!\in_array($cadence, [BriefingCadence::HOURLY, BriefingCadence::DAILY], true)) {
            return;
        }

        if (!$this->appSettings->getBool('article_generation.auto_from_briefing', true)) {
            return;
        }

        $topicId = $topic->getId();
        if ($topicId === null) {
            return;
        }

        $this->messageBus->dispatch(new GenerateTopicArticleMessage(
            topicId: $topicId,
            windowStart: $range->from,
            windowEnd: $range->to,
        ));

        $this->logger->info('GenerateTopicBriefing: chained article proposal dispatched', [
            'topicId' => $topicId,
            'cadence' => $cadence->value,
            'briefingStatus' => $briefingStatus->value,
        ]);
    }
}
