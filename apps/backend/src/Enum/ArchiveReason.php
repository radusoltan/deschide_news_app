<?php

declare(strict_types=1);

namespace App\Enum;

enum ArchiveReason: string
{
    case OLD_CONTENT = 'old_content';           // Content older than 4 years
    case OUTDATED_INFO = 'outdated_info';       // Information no longer relevant
    case LEGAL_REQUEST = 'legal_request';       // Legal or GDPR request
    case DUPLICATE = 'duplicate';               // Duplicate content
    case LOW_QUALITY = 'low_quality';           // Low quality content
    case POLICY_VIOLATION = 'policy_violation'; // Violated editorial policy
    case MANUAL = 'manual';                     // Manual decision by editor

    public function label(): string
    {
        return match ($this) {
            self::OLD_CONTENT => 'Conținut vechi (4+ ani)',
            self::OUTDATED_INFO => 'Informații depășite',
            self::LEGAL_REQUEST => 'Cerere legală/GDPR',
            self::DUPLICATE => 'Conținut duplicat',
            self::LOW_QUALITY => 'Calitate scăzută',
            self::POLICY_VIOLATION => 'Încălcare politică editorială',
            self::MANUAL => 'Decizie manuală editor',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::OLD_CONTENT => 'Articol arhivat automat datorită vârstei (peste 4 ani)',
            self::OUTDATED_INFO => 'Informațiile din articol nu mai sunt actuale sau relevante',
            self::LEGAL_REQUEST => 'Arhivat la cerere legală sau conformare GDPR',
            self::DUPLICATE => 'Conținut duplicat cu alt articol existent',
            self::LOW_QUALITY => 'Calitatea conținutului nu corespunde standardelor actuale',
            self::POLICY_VIOLATION => 'Articolul încalcă politicile editoriale curente',
            self::MANUAL => 'Arhivat manual de către editor',
        };
    }
}
