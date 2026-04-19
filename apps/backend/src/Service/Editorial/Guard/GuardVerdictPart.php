<?php

declare(strict_types=1);

namespace App\Service\Editorial\Guard;

/**
 * Output of a single {@see GuardInterface}::validate() invocation
 * (Sprint 55 T55.6, ADR-020 L4 layer).
 *
 * Aggregated by {@see GuardPipeline} into a final {@see GuardVerdict}.
 *
 * - `failures`: blocking issues. Any non-empty list sets the pipeline verdict
 *   to `passed=false`. Callers (writer handlers in T55.9) leave the Article
 *   in status=NEW and flag it for editor review.
 * - `warnings`: non-blocking signals kept for observability.
 * - `escalationCode`: when set, promotes the article straight to
 *   ESCALATE_HUMAN in the pipeline (Legal Category 6 per ADR-020 D7/D8).
 *   Only guards with a legal lens ever set this.
 */
final readonly class GuardVerdictPart
{
    /**
     * @param list<string> $failures
     * @param list<string> $warnings
     */
    public function __construct(
        public array $failures = [],
        public array $warnings = [],
        public ?string $escalationCode = null,
    ) {}

    public static function pass(): self
    {
        return new self();
    }

    /**
     * @param list<string> $warnings
     */
    public static function passWithWarnings(array $warnings): self
    {
        return new self(warnings: $warnings);
    }

    public function isPassing(): bool
    {
        return $this->failures === [] && $this->escalationCode === null;
    }
}
