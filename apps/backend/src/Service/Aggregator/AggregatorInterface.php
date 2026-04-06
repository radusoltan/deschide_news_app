<?php

declare(strict_types=1);

namespace App\Service\Aggregator;

use App\Dto\Aggregator\AggregatorResult;
use App\Enum\AggregatorSourceType;

interface AggregatorInterface
{
    /** @return AggregatorResult[] */
    public function fetch(): array;

    public function getSourceType(): AggregatorSourceType;

    public function getName(): string;
}
