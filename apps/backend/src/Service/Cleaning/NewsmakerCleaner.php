<?php

declare(strict_types=1);

namespace App\Service\Cleaning;

/**
 * Removes noise from Newsmaker (newsmaker.md) scraped content.
 *
 * Noise: skip-nav, byline, mistape.com widget, NM Espresso promo,
 * Telegram promo, donation blocks, related articles, job listings,
 * spelling report form.
 */
class NewsmakerCleaner implements SourceContentCleanerInterface
{
    public function supports(string $sourceName): bool
    {
        return str_contains(mb_strtolower($sourceName), 'newsmaker');
    }

    public function clean(string $content): string
    {
        // 1. Remove skip navigation
        $content = preg_replace(
            '/<p>\s*(Sari|Treci|Treceți|Salt)\s*(la\s+)?conținut\s*<\/p>/iu',
            '',
            $content,
        ) ?? $content;

        // 2. Remove inline byline at start
        // Pattern: "<p>- Author Name - | DD month, YYYY - HH:MM</p>"
        $content = preg_replace(
            '/<p>\s*-\s+[A-ZĂÂÎȘȚa-zăâîșț\s.-]+\s*-\s*\|\s*\d+.*?\d{4}\s*-\s*\d{2}:\d{2}\s*<\/p>/u',
            '',
            $content,
        ) ?? $content;

        // 3. BOUNDARY: Truncate everything after mistape.com marker
        if (($pos = mb_strpos($content, 'mistape.com')) !== false) {
            $content = mb_substr($content, 0, $pos);
            // Clean up the last incomplete tag
            $content = preg_replace('/<[^>]*$/', '', $content) ?? $content;
        } elseif (($pos = mb_strpos($content, 'NM Espresso')) !== false) {
            // Alternative boundary: NM Espresso promo
            $content = mb_substr($content, 0, $pos);
            $content = preg_replace('/<[^>]*$/', '', $content) ?? $content;
        } elseif (($pos = mb_strpos($content, 'Materiale similare')) !== false) {
            // Alternative: "Similar materials"
            $content = mb_substr($content, 0, $pos);
            $content = preg_replace('/<[^>]*$/', '', $content) ?? $content;
        }

        // 4. Remove Telegram promo if it appears INSIDE article (before boundary)
        $content = preg_replace(
            '/<p>[^<]*Abonați-vă la canalul nostru de Telegram[^<]*<\/p>/iu',
            '',
            $content,
        ) ?? $content;

        // 5. Remove donation blocks inside article
        $content = preg_replace(
            '/<p>[^<]*Doriți să susțineți[^<]*<\/p>/iu',
            '',
            $content,
        ) ?? $content;

        // 6. Remove photo credits standalone paragraphs (e.g., "<p>Stringer/Reuters</p>")
        $content = preg_replace(
            '/<p>\s*[A-Z][a-zA-Z]+\s*\/\s*[A-Z][a-zA-Z]+\s*<\/p>/u',
            '',
            $content,
        ) ?? $content;

        // 7. Remove topic labels (e.g., "<p>- Război în Ucraina</p>")
        $content = preg_replace(
            '/<p>\s*-\s+[A-ZĂÂÎȘȚ][^\n<]{3,50}\s*<\/p>/u',
            '',
            $content,
        ) ?? $content;

        // 8. Remove "Nu mai sunt articole" / "More news" / "Locuri de muncă"
        $content = preg_replace(
            '/<p>\s*(Nu mai sunt articole|More news|Locuri de muncă.*?|Mai multe știri)\s*<\/p>/iu',
            '',
            $content,
        ) ?? $content;

        // 9. Clean up empty tags and excessive whitespace
        $content = preg_replace('/<p>\s*<\/p>/', '', $content) ?? $content;
        $content = preg_replace('/\n{3,}/', "\n\n", trim($content));

        return $content;
    }
}
