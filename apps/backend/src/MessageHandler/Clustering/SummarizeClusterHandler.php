<?php

declare(strict_types=1);

namespace App\MessageHandler\Clustering;

use App\Entity\StoryCluster;
use App\Message\Clustering\SummarizeClusterMessage;
use App\Service\Clustering\ClusterSummaryService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SummarizeClusterHandler
{
    public function __construct(
        private ClusterSummaryService $summaryService,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(SummarizeClusterMessage $message): void
    {
        $cluster = $this->em->find(StoryCluster::class, $message->clusterId);

        if ($cluster === null) {
            $this->logger->warning('SummarizeClusterHandler: cluster #{id} not found', [
                'id' => $message->clusterId,
            ]);
            return;
        }

        $this->summaryService->summarize($cluster);
    }
}
