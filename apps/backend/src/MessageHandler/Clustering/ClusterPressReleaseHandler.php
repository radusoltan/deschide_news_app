<?php

declare(strict_types=1);

namespace App\MessageHandler\Clustering;

use App\Entity\PressRelease;
use App\Message\Clustering\ClusterPressReleaseMessage;
use App\Service\Clustering\ClusteringService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ClusterPressReleaseHandler
{
    public function __construct(
        private EntityManagerInterface $em,
        private ClusteringService $clusteringService,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(ClusterPressReleaseMessage $message): void
    {
        $pr = $this->em->find(PressRelease::class, $message->pressReleaseId);

        if ($pr === null) {
            $this->logger->warning('ClusterPressReleaseHandler: PressRelease #{id} not found', [
                'id' => $message->pressReleaseId,
            ]);
            return;
        }

        $this->clusteringService->clusterSinglePressRelease($pr);
        $this->em->flush();

        $this->logger->info('ClusterPressReleaseHandler: processed PressRelease #{id}', [
            'id' => $message->pressReleaseId,
        ]);
    }
}
