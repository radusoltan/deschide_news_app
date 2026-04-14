<?php

declare(strict_types=1);

namespace App\Service\Cleaning;

/**
 * Removes noise from Zugo (zugo.md) scraped content.
 *
 * Noise: social share buttons, author block, Telegram promo.
 */
class ZugoCleaner implements SourceContentCleanerInterface
{
    public function supports(string $sourceName): bool
    {
        return str_contains(mb_strtolower($sourceName), 'zugo');
    }

    public function clean(string $content): string
    {
        // 1. Remove social share text block
        $content = preg_replace(
            '/<p>\s*Facebook\s+Twitter\s+LinkedIn.*?WhatsApp\s+Telegram\s*<\/p>/iu',
            '',
            $content,
        ) ?? $content;

        // 2. Remove "Redacția ZUGO" author block with time reference
        $content = preg_replace(
            '/<p>\s*Redacția ZUGO\s*\d+\s*ore?\s+în urmă\s*<\/p>/iu',
            '',
            $content,
        ) ?? $content;

        // 3. Remove Telegram/Instagram promo
        $content = preg_replace(
            '/<p>[^<]*Urmărește-ne pe Telegram și Instagram[^<]*<\/p>/iu',
            '',
            $content,
        ) ?? $content;

        // 4. Remove sharing CTA lines
        $content = preg_replace(
            '/<p>\s*(Distribuie|Share|Partajează)\s*:?\s*<\/p>/iu',
            '',
            $content,
        ) ?? $content;

        // 5. Clean up
        $content = preg_replace('/<p>\s*<\/p>/', '', $content) ?? $content;
        $content = preg_replace('/\n{3,}/', "\n\n", trim($content));

        return $content;
    }
}
