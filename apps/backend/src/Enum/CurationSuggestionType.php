<?php

declare(strict_types=1);

namespace App\Enum;

enum CurationSuggestionType: string
{
    case MERGE = 'merge';
    case ARCHIVE = 'archive';
    case RETAG = 'retag';
}
