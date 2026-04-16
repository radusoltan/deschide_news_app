<?php

declare(strict_types=1);

namespace App\MessageHandler\Topic;

use App\Entity\Topic;
use App\Message\Topic\GenerateTopicBriefingMessage;
use App\Service\Editorial\TopicBriefingWriterService;
use App\ValueObject\DateRange;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Per-topic briefing generation handler.
 *
 * Receives dispatched messages from TriggerTopicBriefingRunHandler
 * and delegates to TopicBriefingWriterService for the dual-LLM chain.
 */
#[AsMessageHandler]
final readonly class GenerateTopicBriefingHandler
{
    public function __construct(
        private TopicBriefingWriterService $writerService,
        private EntityManagerInterface $em,
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
    }
}
