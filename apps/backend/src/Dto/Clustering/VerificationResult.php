<?php

declare(strict_types=1);

namespace App\Dto\Clustering;

class VerificationResult
{
    public function __construct(
        public readonly bool $sameStory,
        public readonly float $confidence,
        public readonly string $reason,
        public readonly bool $failedOpen = false,
    ) {}

    public static function pass(string $reason = ''): self
    {
        return new self(true, 1.0, $reason);
    }

    public static function reject(float $confidence, string $reason): self
    {
        return new self(false, $confidence, $reason);
    }

    public static function failOpen(string $reason): self
    {
        return new self(true, 0.5, $reason, true);
    }
}
