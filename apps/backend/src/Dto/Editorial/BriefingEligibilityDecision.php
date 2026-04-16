<?php

declare(strict_types=1);

namespace App\Dto\Editorial;

use App\Enum\BriefingCadence;

/**
 * Result of BriefingEligibilityGateService evaluation for a topic+cadence pair.
 */
final readonly class BriefingEligibilityDecision
{
    /**
     * @param list<string> $reasons  Reason codes explaining why the topic was blocked (empty if eligible)
     */
    public function __construct(
        public bool $eligible,
        public BriefingCadence $cadence,
        public int $prCount,
        public float $avgRelevance,
        public array $reasons,
    ) {}
}
