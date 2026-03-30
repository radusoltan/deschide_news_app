<?php

declare(strict_types=1);

namespace App\Enum;

enum MenuType: string
{
    case MAIN = 'main';
    case FOOTER = 'footer';
}
