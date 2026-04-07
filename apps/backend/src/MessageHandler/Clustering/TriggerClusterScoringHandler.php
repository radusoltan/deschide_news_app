<?php

declare(strict_types=1);

namespace App\MessageHandler\Clustering;

use App\Entity\StoryCluster;
use App\Enum\StoryClusterStatus;
use App\Message\Clustering\TriggerClusterScoringMessage;
use App\Repository\StoryClusterRepository;
use App\Service\Clustering\ImportanceScoreCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class TriggerClusterScoringHandler
{
    private const AUTO_PROMOTE_THRESHOLD = 0.7;

    public function __construct(
        private EntityManagerInterface $em,
        private StoryClusterRepository $clusterRepository,
        private ImportanceScoreCalculator $calculator,
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

            if ($message->autoPromote
                && $newScore >= self::AUTO_PROMOTE_THRESHOLD
                && $cluster->getStatus() === StoryClusterStatus::AUTO
                && !$cluster->isPromotedToPressRelease()
            ) {
                $cluster->setStatus(StoryClusterStatus::PROMOTED);
                $cluster->setPromotedToPressRelease(true);
                $promoted++;
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
