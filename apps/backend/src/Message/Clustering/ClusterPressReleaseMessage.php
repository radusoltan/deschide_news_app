<?php

declare(strict_types=1);

namespace App\Message\Clustering;

final readonly class ClusterPressReleaseMessage
{
    public function __construct(
        public int $pressReleaseId,
    ) {}
}
