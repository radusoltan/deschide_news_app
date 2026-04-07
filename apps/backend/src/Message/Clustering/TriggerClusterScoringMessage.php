<?php

declare(strict_types=1);

namespace App\Message\Clustering;

final readonly class TriggerClusterScoringMessage
{
    public function __construct(
        public string $since = '48h',
        public bool $autoPromote = true,
    ) {}
}
