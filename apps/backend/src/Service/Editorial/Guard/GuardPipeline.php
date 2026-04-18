<?php

declare(strict_types=1);

namespace App\Service\Editorial\Guard;

use App\Entity\Article;

/**
 * Composes all tagged {@see GuardInterface} services in service-definition
 * order and aggregates their output into a final {@see GuardVerdict}
 * (Sprint 55 T55.6, ADR-020 L4 layer).
 *
 * Aggregation rules:
 *  - Failures + warnings from every guard are concatenated in the order
 *    they were produced.
 *  - `passed = (failures === [] && escalationCode === null)`.
 *  - `escalationCode` is promoted from the FIRST guard that sets one —
 *    ordering matters if multiple guards could escalate (unlikely in S55
 *    where only LegalGuard escalates under Category 6).
 */
class GuardPipeline implements GuardPipelineInterface
{
    /** @var list<GuardInterface> */
    private readonly array $guards;

    /**
     * @param iterable<GuardInterface> $guards
     */
    public function __construct(iterable $guards)
    {
        $this->guards = is_array($guards) ? array_values($guards) : iterator_to_array($guards, false);
    }

    public function check(Article $article, array $context = []): GuardVerdict
    {
        $failures = [];
        $warnings = [];
        $escalationCode = null;

        foreach ($this->guards as $guard) {
            $part = $guard->validate($article, $context);
            foreach ($part->failures as $failure) {
                $failures[] = $failure;
            }
            foreach ($part->warnings as $warning) {
                $warnings[] = $warning;
            }
            if ($escalationCode === null && $part->escalationCode !== null) {
                $escalationCode = $part->escalationCode;
            }
        }

        $passed = $failures === [] && $escalationCode === null;

        return new GuardVerdict(
            passed: $passed,
            failures: $failures,
            warnings: $warnings,
            escalationCode: $escalationCode,
        );
    }
}
