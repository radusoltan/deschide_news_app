<?php

declare(strict_types=1);

namespace App\Service\Editorial\Guard;

use App\Enum\Editorial\EscalationCategory;

/**
 * Maps {@see GuardVerdictPart::$escalationCode} string codes emitted by the
 * L4 guards to the {@see EscalationCategory} enum expected by
 * {@see \App\Service\Editorial\Escalation\EscalationLogWriter}
 * (Sprint 55 T55.9).
 *
 * Guards don't know about EscalationCategory (the escalation layer was built
 * later and we kept the guard layer enum-free to avoid a circular dependency).
 * This mapper is the single seam between the two.
 */
final class GuardEscalationCategoryMapper
{
    /**
     * @param string $escalationCode raw code emitted by GuardVerdictPart
     * @return EscalationCategory category the EscalationLogWriter should persist
     */
    public function map(string $escalationCode): EscalationCategory
    {
        return match ($escalationCode) {
            LegalGuard::ESCALATION_CODE_CATEGORY_6,
            LegalGuard::ESCALATION_CODE_UNAVAILABLE => EscalationCategory::CATEGORY_6_CRIMINAL_ACCUSATION,
            default => EscalationCategory::CATEGORY_6_CRIMINAL_ACCUSATION,
        };
    }
}
