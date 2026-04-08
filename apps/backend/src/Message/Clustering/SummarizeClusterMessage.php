<?php

declare(strict_types=1);

namespace App\Message\Clustering;

class SummarizeClusterMessage
{
    public function __construct(
        public readonly int $clusterId,
    ) {}
}
