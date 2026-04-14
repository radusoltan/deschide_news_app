<?php

declare(strict_types=1);

namespace App\Service;

class ContentHasher
{
    public function hash(string $htmlContent): string
    {
        $text = strip_tags($htmlContent);
        $text = $this->normalize($text);

        return hash('sha256', $text);
    }

    /**
     * Normalize text before hashing to ensure identical articles
     * with minor dynamic differences produce the same hash.
     */
    private function normalize(string $text): string
    {
        // Strip view counters: "148 vizualizări", "152 views", "203 просмотра"
        $text = preg_replace('/\d+\s*(vizualiz[aă]ri|views|просмотр[а-яё]*)/ui', '', $text);

        // Strip year-followed-by-counter pattern: "2026 148" → "2026"
        // After strip_tags, counter may be glued to next word: "2026 148Full" or "2026 148 Full"
        $text = preg_replace('/(\d{4})\s+\d{1,6}(?=\s|[A-ZА-Яa-zа-я]|$)/u', '$1', $text);

        // Whitespace normalization
        $text = preg_replace('/\s+/', ' ', $text);
        $text = mb_strtolower(trim($text));

        return $text;
    }
}
