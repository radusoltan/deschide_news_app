<?php

declare(strict_types=1);

namespace App\Enum;

enum TranslationPriority: int
{
    case CRITICAL = 0;  // Breaking / Alert / Flash badges
    case URGENT = 1;    // Important Articles (hero section)
    case HIGH = 2;      // Editoriale category
    case NORMAL = 3;    // All other articles

    public function label(): string
    {
        return match ($this) {
            self::CRITICAL => 'critical',
            self::URGENT => 'urgent',
            self::HIGH => 'high',
            self::NORMAL => 'normal',
        };
    }

    public function isInstant(): bool
    {
        return $this === self::CRITICAL || $this === self::URGENT;
    }
}
