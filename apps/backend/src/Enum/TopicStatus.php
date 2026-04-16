<?php

declare(strict_types=1);

namespace App\Enum;

enum TopicStatus: string
{
    case ACTIVE = 'active';
    case ARCHIVED = 'archived';
    case PROPOSED = 'proposed';
}
