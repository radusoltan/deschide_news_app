<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Outcome of a claim observed on a {@see App\Entity\Editorial\VerifiedSource}
 * after verification. Populated by Sprint 54+ services; enum defined in
 * Sprint 53 so the schema-only `SourceClaimHistory` rows have a valid type.
 */
enum ClaimOutcome: string
{
    case CONFIRMED = 'confirmed';
    case INFIRMED  = 'infirmed';
    case UNRESOLVED = 'unresolved';
}
