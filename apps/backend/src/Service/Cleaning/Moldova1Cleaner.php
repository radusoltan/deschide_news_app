<?php

declare(strict_types=1);

namespace App\Service\Cleaning;

/**
 * Removes noise from Moldova1 (moldova1.md) scraped content.
 *
 * Noise: date stamps, related article links at end.
 */
class Moldova1Cleaner implements SourceContentCleanerInterface
{
    public function supports(string $sourceName): bool
    {
        return str_contains(mb_strtolower($sourceName), 'moldova1');
    }

    public function clean(string $content): string
    {
        // 1. Remove "Publicat DD.MM.YYYY HH:MM" date stamps
        $content = preg_replace(
            '/<p>\s*Publicat\s+\d{2}\.\d{2}\.\d{4}\s+\d{2}:\d{2}\s*<\/p>/u',
            '',
            $content,
        ) ?? $content;

        // 2. Remove "Autor:" standalone paragraphs
        $content = preg_replace(
            '/<p>\s*Autor\s*:\s*[^<]+<\/p>/iu',
            '',
            $content,
        ) ?? $content;

        // 3. Remove related article links at end (short paragraphs with just a title)
        // These typically appear as the last few paragraphs and match article titles
        $content = preg_replace(
            '/<p>\s*Vezi (și|mai mult)\s*:?\s*<\/p>/iu',
            '',
            $content,
        ) ?? $content;

        // 4. Remove "Sursa:" attribution
        $content = preg_replace(
            '/<p>\s*Sursa\s*:\s*Moldova\s*1\s*<\/p>/iu',
            '',
            $content,
        ) ?? $content;

        // 5. Clean up
        $content = preg_replace('/<p>\s*<\/p>/', '', $content) ?? $content;
        $content = preg_replace('/\n{3,}/', "\n\n", trim($content));

        return $content;
    }
}
