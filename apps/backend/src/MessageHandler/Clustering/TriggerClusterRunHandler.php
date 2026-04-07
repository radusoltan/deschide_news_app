<?php

declare(strict_types=1);

namespace App\MessageHandler\Clustering;

use App\Message\Clustering\TriggerClusterRunMessage;
use App\Service\Clustering\ClusteringService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class TriggerClusterRunHandler
{
    public function __construct(
        private ClusteringService $clusteringService,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(TriggerClusterRunMessage $message): void
    {
        $since = $this->parseSince($message->since);
        $processed = $this->clusteringService->clusterNewPressReleases($since);

        $this->logger->info('TriggerClusterRunHandler: processed {count} PressReleases', [
            'count' => $processed,
        ]);
    }

    private function parseSince(string $since): \DateTimeImmutable
    {
        if (preg_match('/^(\d+)h$/', $since, $m)) {
            return new \DateTimeImmutable(sprintf('-%d hours', (int) $m[1]));
        }
        if (preg_match('/^(\d+)d$/', $since, $m)) {
            return new \DateTimeImmutable(sprintf('-%d days', (int) $m[1]));
        }

        return new \DateTimeImmutable('-24 hours');
    }
}
