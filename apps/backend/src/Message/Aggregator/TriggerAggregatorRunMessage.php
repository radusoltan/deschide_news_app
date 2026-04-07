<?php

declare(strict_types=1);

namespace App\Message\Aggregator;

final readonly class TriggerAggregatorRunMessage
{
    public function __construct(
        public ?string $source = null,  // null = all sources
        public string $triggeredBy = 'manual',
    ) {}
}
