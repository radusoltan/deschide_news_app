<?php

declare(strict_types=1);

namespace App\Enum;

enum ArticleStatus: string
{
    case NEW = 'new';
    case SUBMITTED = 'submitted';
    case PUBLISHED = 'published';
    case PUBLISHED_FULL = 'published_full';
    case ARCHIVED = 'archived';
}
