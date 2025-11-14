<?php

declare(strict_types=1);

namespace App\Enum;

enum ArticleBadge: string
{
    case BREAKING = 'breaking';
    case ALERT = 'alert';
    case FLASH = 'flash';
}
