<?php

declare(strict_types=1);

namespace App\Enum;

enum KeywordAddedBy: string
{
    case MANUAL = 'manual';
    case AI = 'ai';
    case TREND = 'trend';
}
