<?php

declare(strict_types=1);

namespace App\Enum;

enum AuthorType: string
{
    public function label(): string
    {
        return match ($this) {
            self::JOURNALIST => 'Jurnalist',
            self::EDITORIALIST => 'Editorialist',
            self::AGENCY => 'Agenție de știri',
            self::PRESS_OFFICE => 'Oficiu de presă',
        };
    }

    case JOURNALIST = 'journalist';
    case EDITORIALIST = 'editorialist';
    case AGENCY = 'agency';
    case PRESS_OFFICE = 'press_office';
}
