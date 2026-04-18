<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Final decision recorded on an editorial escalation. Populated by
 * Sprint 55 workflow; enum defined in Sprint 53 so the schema-only
 * `EditorialEscalationLog` rows have a valid type.
 */
enum EscalationDecision: string
{
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case EXPIRED  = 'expired';
}
