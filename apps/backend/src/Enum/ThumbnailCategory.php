<?php

declare(strict_types=1);

namespace App\Enum;

enum ThumbnailCategory: string
{
    case ARTICLE = 'article';   // Thumbnails pentru imagini de articole
    case PROFILE = 'profile';   // Thumbnails pentru imagini de profil (autori)
    case SOCIAL = 'social';     // Thumbnails pentru social media (Open Graph, Twitter Cards)
    case GENERAL = 'general';   // Thumbnails generale (ambele)
}
