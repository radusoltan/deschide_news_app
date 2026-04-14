<?php

declare(strict_types=1);

namespace App\Service\Cleaning;

/**
 * Removes noise from Agerpres (agerpres.ro) scraped content.
 *
 * Noise: site header/nav, social share URLs, related articles,
 * copyright blocks, sidebar items, time-stamped article list.
 */
class AgerpressCleaner implements SourceContentCleanerInterface
{
    public function supports(string $sourceName): bool
    {
        return str_contains(mb_strtolower($sourceName), 'agerpres');
    }

    public function clean(string $content): string
    {
        // 1. Strip the site header block (everything before the first real article paragraph)
        // The header contains: "Agerpres – Agenția Națională de Presă", nav links, social icons
        $content = preg_replace(
            '/^.*?Agenția Națională de Presă[^<]*(<\/p>)?/isu',
            '',
            $content,
        ) ?? $content;

        // 2. Remove nav/menu lines (Acasă, Abonați, Foto Agerpres, etc.)
        $content = preg_replace(
            '/<p>\s*-?\s*(Acasă|Abonați|Foto Agerpres|Monitorizare de presă)\s*-?\s*.*?<\/p>/iu',
            '',
            $content,
        ) ?? $content;

        // 3. Remove social share URLs
        $content = preg_replace(
            '/<p>[^<]*(facebook\.com\/sharer|linkedin\.com\/shareArticle|twitter\.com\/intent|whatsapp\.com\/channel)[^<]*<\/p>/iu',
            '',
            $content,
        ) ?? $content;

        // 4. Remove Google News channel links
        $content = preg_replace(
            '/<p>[^<]*news\.google\.com\/publications[^<]*<\/p>/iu',
            '',
            $content,
        ) ?? $content;

        // 5. BOUNDARY: Truncate after "Alte știri din categorie" or related articles section
        if (($pos = mb_strpos($content, 'Alte știri din categorie')) !== false) {
            $content = mb_substr($content, 0, $pos);
            $content = preg_replace('/<[^>]*$/', '', $content) ?? $content;
        }

        // 6. BOUNDARY: Truncate after copyright block
        if (($pos = mb_strpos($content, 'Conținutul website-ului www.agerpres.ro')) !== false) {
            $content = mb_substr($content, 0, $pos);
            $content = preg_replace('/<[^>]*$/', '', $content) ?? $content;
        }

        // 7. Remove timestamped article list items (e.g., "10:38 - Fotbal: Villareal...")
        $content = preg_replace(
            '/<p>\s*\d{2}:\d{2}\s*-\s+.*?<\/p>/u',
            '',
            $content,
        ) ?? $content;

        // 8. Remove sidebar/press release items
        $content = preg_replace(
            '/<p>\s*Comunicat de presă\s*-\s*.*?<\/p>/iu',
            '',
            $content,
        ) ?? $content;

        // 9. Remove category breadcrumbs (e.g., "Politică > Politică externă")
        $content = preg_replace(
            '/<p>\s*[A-ZĂÂÎȘȚ][a-zăâîșț]+\s*>\s*[A-ZĂÂÎȘȚ].*?<\/p>/u',
            '',
            $content,
        ) ?? $content;

        // 10. Remove standalone date paragraphs
        $content = preg_replace(
            '/<p>\s*\d{2}\.\d{2}\.\d{4}\s*<\/p>/',
            '',
            $content,
        ) ?? $content;

        // 11. Clean up
        $content = preg_replace('/<p>\s*<\/p>/', '', $content) ?? $content;
        $content = preg_replace('/\n{3,}/', "\n\n", trim($content));

        return $content;
    }
}
