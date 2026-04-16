<?php

declare(strict_types=1);

namespace App\Enum;

enum BriefingCadence: string
{
    case HOURLY = 'hourly';
    case DAILY = 'daily';
    case WEEKLY = 'weekly';
}
