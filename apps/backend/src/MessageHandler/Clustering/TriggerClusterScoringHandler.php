<?php

declare(strict_types=1);

namespace App\MessageHandler\Clustering;

use App\Entity\StoryCluster;
use App\Enum\StoryClusterStatus;
use App\Message\Clustering\TriggerClusterScoringMessage;
use App\Repository\StoryClusterRepository;
use App\Service\Clustering\AutoPromoteService;
use App\Service\Clustering\ImportanceScoreCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class TriggerClusterScoringHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private StoryClusterRepository $clusterRepository,
        private ImportanceScoreCalculator $calculator,
        private AutoPromoteService $autoPromoteService,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(TriggerClusterScoringMessage $message): void
    {
        $since = $this->parseSince($message->since);
        $clusters = $this->clusterRepository->findActiveClustersInWindow($since);

        $scored = 0;
        $promoted = 0;

        foreach ($clusters as $cluster) {
            $newScore = $this->calculator->calculate($cluster);
            $cluster->setImportanceScore($newScore);
            $scored++;

            if ($message->autoPromote && $this->autoPromoteService->isEligible($cluster)) {
                $pr = $this->autoPromoteService->promoteCluster($cluster);
                if ($pr !== null) {
                    $promoted++;
                }
            }
        }

        $this->em->flush();

        $this->logger->info('TriggerClusterScoringHandler: scored {scored}, promoted {promoted}', [
            'scored' => $scored,
            'promoted' => $promoted,
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

        return new \DateTimeImmutable('-48 hours');
    }
}
