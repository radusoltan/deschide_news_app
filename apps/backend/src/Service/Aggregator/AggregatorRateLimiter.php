<?php

declare(strict_types=1);

namespace App\Service\Aggregator;

use Symfony\Component\RateLimiter\RateLimiterFactory;

class AggregatorRateLimiter
{
    public function __construct(
        private readonly RateLimiterFactory $aggregatorGoogleNewsLimiter,
    ) {}

    public function isAllowed(string $key): bool
    {
        $limiter = $this->aggregatorGoogleNewsLimiter->create($key);

        return $limiter->consume(1)->isAccepted();
    }
}
