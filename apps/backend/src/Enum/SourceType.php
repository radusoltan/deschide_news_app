<?php

declare(strict_types=1);

namespace App\Enum;

enum SourceType: string
{
    case EMAIL = 'email';
    case SCRAPE = 'scrape';
    case MANUAL = 'manual';
    case AGGREGATOR = 'aggregator';
}
