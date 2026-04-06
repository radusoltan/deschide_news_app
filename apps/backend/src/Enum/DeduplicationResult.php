<?php

declare(strict_types=1);

namespace App\Enum;

enum DeduplicationResult: string
{
    case UNIQUE = 'unique';
    case DUPLICATE = 'duplicate';
    case NEEDS_REVIEW = 'needs_review';
}
