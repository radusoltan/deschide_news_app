<?php

declare(strict_types=1);

namespace App\Enum;

enum StoryClusterStatus: string
{
    case AUTO = 'auto';
    case REVIEWED = 'reviewed';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';
    case PROMOTED = 'promoted';
    case ARCHIVED = 'archived';
}
