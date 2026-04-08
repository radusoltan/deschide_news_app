<?php

declare(strict_types=1);

namespace App\Enum;

enum SourceCategory: string
{
    case AGENCY = 'agency';
    case GENERALIST = 'generalist';
    case BUSINESS = 'business';
    case GEOPOLITICS = 'geopolitics';
    case REGIONAL = 'regional';
    case LOCAL = 'local';
    case INSTITUTIONAL = 'institutional';
    case DIASPORA = 'diaspora';
}
