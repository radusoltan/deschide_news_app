<?php

declare(strict_types=1);

namespace App\Enum;

enum ThumbnailMode: string
{
    case COVER = 'cover';   // Cover - resize și crop pentru a umple exact dimensiunile (default)
    case CONTAIN = 'contain'; // Contain - resize pentru a încăpea în dimensiuni (păstrează tot conținutul)
    case CROP = 'crop';     // Crop - crop direct la dimensiuni exacte
    case SCALE = 'scale';   // Scale - resize simplu fără aspect ratio lock
    case FIT = 'fit';       // Fit - alias pentru contain (deprecated)
    case FILL = 'fill';     // Fill - stretches imaginea dacă e necesar (deprecated)
}
