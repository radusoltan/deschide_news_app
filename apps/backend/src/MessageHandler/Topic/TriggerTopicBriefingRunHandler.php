<?php

declare(strict_types=1);

namespace App\MessageHandler\Topic;

use App\Entity\Topic;
use App\Enum\TopicStatus;
use App\Message\Topic\GenerateTopicBriefingMessage;
use App\Message\Topic\TriggerTopicBriefingRunMessage;
use App\Service\Editorial\BriefingEligibilityGateService;
use App\ValueObject\DateRange;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Fan-out handler: iterates active topics, applies eligibility gate,
 * dispatches per-topic generation messages for eligible topics.
 *
 * This 2-level pattern (ADR-016 D2) runs eligibility BEFORE dispatch,
 * avoiding 164+ simultaneous worker jobs when ~90% of topics won't qualify.
 */
#[AsMessageHandler]
final readonly class TriggerTopicBriefingRunHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private BriefingEligibilityGateService $gate,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(TriggerTopicBriefingRunMessage $message): void
    {
        $cadence = $message->cadence;
        $range = DateRange::forCadence($cadence);

        $this->logger->info('TriggerTopicBriefingRun: starting {cadence} run', [
            'cadence' => $cadence->value,
            'from' => $range->from->format('c'),
            'to' => $range->to->format('c'),
        ]);

        $topics = $this->em->getRepository(Topic::class)->findBy([
            'isActive' => true,
            'status' => TopicStatus::ACTIVE,
        ]);

        $dispatched = 0;
        $filtered = 0;

        foreach ($topics as $topic) {
            $decision = $this->gate->evaluate($topic, $cadence, $range);

            if (!$decision->eligible) {
                $filtered++;
                continue;
            }

            $this->messageBus->dispatch(new GenerateTopicBriefingMessage(
                topicId: $topic->getId(),
                cadence: $cadence,
                periodFrom: $range->from->format('c'),
                periodTo: $range->to->format('c'),
            ));

            $dispatched++;
        }

        $this->logger->info('TriggerTopicBriefingRun: completed {cadence} run', [
            'cadence' => $cadence->value,
            'totalTopics' => \count($topics),
            'dispatched' => $dispatched,
            'filtered' => $filtered,
        ]);
    }
}
