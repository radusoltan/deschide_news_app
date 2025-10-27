<?php

declare(strict_types=1);

namespace App\Enum;

enum ThumbnailMode: string
{
    case CROP = 'crop';   // Crop imaginea la dimensiuni exacte (poate pierde margini)
    case FIT = 'fit';     // Fit imaginea în dimensiuni (păstrează aspect ratio, poate avea margini)
    case FILL = 'fill';   // Fill dimensiunile (stretches imaginea dacă e necesar)
}
