<?php

declare(strict_types=1);

namespace App\Enum;

enum MenuItemType: string
{
    case CATEGORY = 'category';
    case EXTERNAL_LINK = 'external_link';
}
