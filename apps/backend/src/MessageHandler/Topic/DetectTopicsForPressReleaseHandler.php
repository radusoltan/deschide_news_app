<?php

declare(strict_types=1);

namespace App\MessageHandler\Topic;

use App\Message\Topic\DetectTopicsForPressReleaseMessage;
use App\Repository\PressReleaseRepository;
use App\Service\Topic\PressReleaseTopicDetector;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[AsMessageHandler]
final readonly class DetectTopicsForPressReleaseHandler
{
    public function __construct(
        private PressReleaseRepository $pressReleaseRepository,
        private PressReleaseTopicDetector $detector,
        private LoggerInterface $logger,
        private ?RateLimiterFactory $topicDetectionLimiter = null,
    ) {
    }

    public function __invoke(DetectTopicsForPressReleaseMessage $message): void
    {
        // Rate limit: max 1/sec to avoid saturating Gemini fallback
        if ($this->topicDetectionLimiter !== null) {
            $limiter = $this->topicDetectionLimiter->create('topic_detection');
            $limiter->reserve(1)->wait();
        }

        $pr = $this->pressReleaseRepository->find($message->pressReleaseId);

        if ($pr === null) {
            $this->logger->warning('DetectTopicsForPressReleaseHandler: PR #{id} not found', [
                'id' => $message->pressReleaseId,
            ]);

            return;
        }

        // Skip if already has topic pivots
        if (!$pr->getPressReleaseTopics()->isEmpty()) {
            $this->logger->debug('DetectTopicsForPressReleaseHandler: PR #{id} already tagged, skipping', [
                'id' => $message->pressReleaseId,
            ]);

            return;
        }

        try {
            $pivots = $this->detector->detectAndPersist($pr);

            $this->logger->info('DetectTopicsForPressReleaseHandler: tagged PR #{id} with {count} topics', [
                'id' => $message->pressReleaseId,
                'count' => \count($pivots),
                'topics' => array_map(
                    static fn ($p) => $p->getTopic()->getTitle(),
                    $pivots,
                ),
            ]);
        } catch (\Throwable $e) {
            $this->logger->warning('DetectTopicsForPressReleaseHandler: failed for PR #{id}: {error}', [
                'id' => $message->pressReleaseId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
