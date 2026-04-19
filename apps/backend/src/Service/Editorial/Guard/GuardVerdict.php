<?php

declare(strict_types=1);

namespace App\Service\Editorial\Guard;

/**
 * Aggregated output of {@see GuardPipeline}::check() across all configured guards
 * (Sprint 55 T55.6, ADR-020 L4 layer).
 *
 * The pipeline verdict drives writer-handler branching in T55.9:
 *   - `passed=true`  → Article proceeds through PostApprovalDispatcher / translations
 *   - `passed=false + escalationCode=null` → Article stays status=NEW, flagged for editor
 *   - `escalationCode != null` → Article archived, EditorialEscalationLog row inserted
 */
final readonly class GuardVerdict
{
    /**
     * @param list<string> $failures
     * @param list<string> $warnings
     */
    public function __construct(
        public bool $passed,
        public array $failures = [],
        public array $warnings = [],
        public ?string $escalationCode = null,
    ) {}
}
