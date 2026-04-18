<?php

declare(strict_types=1);

namespace App\Enum\Editorial;

/**
 * Outcome of {@see \App\Service\Editorial\Verification\VerificationGate}
 * (Sprint 54 T54.9, ADR-020 D3 publication matrix).
 *
 * - FLASH_WITH_ATTRIBUTION       — 1 chain + ≥1 tier-1 source. Safe to publish
 *                                   as a flash with explicit source attribution.
 * - FLASH_WITH_ASSERTION_YELLOW  — 2+ chains + ≥1 tier-1 but all same editorial
 *                                   alignment. Publishable with a "yellow"
 *                                   caveat flag that coverage is single-perspective.
 * - FULL_FLASH                   — 2+ chains + ≥1 tier-1 + alignment-diverse.
 *                                   Highest-confidence verdict, standard flash.
 * - ESCALATE_HUMAN               — high-stakes keyword match OR ambiguous gate
 *                                   result. Never auto-publishes; always waits
 *                                   for editorial review.
 * - REJECT                       — insufficient corroboration (no tier-1 chain
 *                                   or echo-chamber-only coverage). Signal is
 *                                   persisted for audit but does not drive
 *                                   downstream article generation.
 */
enum VerdictType: string
{
    case FLASH_WITH_ATTRIBUTION = 'flash_with_attribution';
    case FLASH_WITH_ASSERTION_YELLOW = 'flash_with_assertion_yellow';
    case FULL_FLASH = 'full_flash';
    case ESCALATE_HUMAN = 'escalate_human';
    case REJECT = 'reject';

    /**
     * Strictness rank used by {@see \App\Service\Editorial\Verification\VerificationGate}
     * to constrain LLM override direction: the LLM may only override the
     * rule-based verdict with an equal-or-stricter alternative. This
     * enforces "AI proposes, editor decides" — the rule-based verdict
     * sets a ceiling, and LLM sanity can only tighten it.
     *
     *   REJECT (0)         < strictest, publishes nothing
     *   ESCALATE_HUMAN (1) < human decides
     *   FLASH_WITH_ASSERTION_YELLOW (2) < publishes with caveat
     *   FLASH_WITH_ATTRIBUTION (3) < publishes with attribution
     *   FULL_FLASH (4)     < most permissive
     */
    public function rank(): int
    {
        return match ($this) {
            self::REJECT => 0,
            self::ESCALATE_HUMAN => 1,
            self::FLASH_WITH_ASSERTION_YELLOW => 2,
            self::FLASH_WITH_ATTRIBUTION => 3,
            self::FULL_FLASH => 4,
        };
    }
}
