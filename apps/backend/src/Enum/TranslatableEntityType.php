<?php

declare(strict_types=1);

namespace App\Enum;

enum TranslatableEntityType: string
{
    case CATEGORY = 'category';
    case AUTHOR = 'author';
}
