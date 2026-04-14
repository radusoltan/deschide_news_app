<?php

declare(strict_types=1);

namespace App\Service\Cleaning;

/**
 * Removes noise from TV8 (tv8.md) scraped content.
 *
 * Trafilatura extraction is often broken for TV8, extracting only boilerplate.
 * This cleaner strips the "Despre TV8" footer and validates remaining content.
 */
class TV8Cleaner implements SourceContentCleanerInterface
{
    public function supports(string $sourceName): bool
    {
        return str_contains(mb_strtolower($sourceName), 'tv8');
    }

    public function clean(string $content): string
    {
        // 1. BOUNDARY: Truncate "Despre TV8" boilerplate
        if (($pos = mb_strpos($content, 'Despre TV8')) !== false) {
            $content = mb_substr($content, 0, $pos);
            $content = preg_replace('/<[^>]*$/', '', $content) ?? $content;
        }

        // 2. Remove "tv8.md este site-ul" boilerplate
        $content = preg_replace(
            '/<p>[^<]*tv8\.md\s+este\s+site-ul[^<]*<\/p>/iu',
            '',
            $content,
        ) ?? $content;

        // 3. Remove social media follow prompts
        $content = preg_replace(
            '/<p>[^<]*(Urmărește-ne pe|Abonează-te la)\s+(Facebook|Instagram|Telegram|YouTube)[^<]*<\/p>/iu',
            '',
            $content,
        ) ?? $content;

        // 4. Remove video embed markers
        $content = preg_replace(
            '/<p>\s*(VIDEO|LIVE|FOTO)\s*<\/p>/i',
            '',
            $content,
        ) ?? $content;

        // 5. Clean up
        $content = preg_replace('/<p>\s*<\/p>/', '', $content) ?? $content;
        $content = preg_replace('/\n{3,}/', "\n\n", trim($content));

        return $content;
    }
}
