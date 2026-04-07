<?php

declare(strict_types=1);

namespace App\Message\Clustering;

final readonly class TriggerClusterRunMessage
{
    public function __construct(
        public string $since = '24h',
    ) {}
}
