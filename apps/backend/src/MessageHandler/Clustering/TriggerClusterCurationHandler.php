<?php

declare(strict_types=1);

namespace App\MessageHandler\Clustering;

use App\Message\Clustering\TriggerClusterCurationMessage;
use App\Service\Clustering\ClusterCuratorService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class TriggerClusterCurationHandler
{
    public function __construct(
        private ClusterCuratorService $curatorService,
    ) {}

    public function __invoke(TriggerClusterCurationMessage $message): void
    {
        $this->curatorService->curate();
    }
}
