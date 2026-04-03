<?php

declare(strict_types=1);

namespace App\Service;

class ContentHasher
{
    public function hash(string $htmlContent): string
    {
        $text = strip_tags($htmlContent);
        $text = preg_replace('/\s+/', ' ', $text);
        $text = mb_strtolower(trim($text));

        return hash('sha256', $text);
    }
}
