<?php

declare(strict_types=1);

namespace App\Enum;

enum LiveTextStatus: string
{
    case DRAFT = 'draft';
    case LIVE = 'live';
    case PAUSED = 'paused';
    case ENDED = 'ended';
}
