<?php

declare(strict_types=1);

namespace App\Enum;

enum BriefingStatus: string
{
    case PENDING = 'pending';
    case GENERATING = 'generating';
    case DRAFT = 'draft';
    case POLISHED = 'polished';
    case PUBLISHED = 'published';
    case FAILED = 'failed';
}
