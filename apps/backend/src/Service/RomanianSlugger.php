<?php

declare(strict_types=1);

namespace App\Service;

use Behat\Transliterator\Transliterator;

/**
 * Romanian-aware slug generator.
 *
 * This service provides proper transliteration for Romanian characters
 * that are not handled correctly by the default Behat\Transliterator::urlize() method.
 *
 * The issue is that urlize() uses unaccent() which has a hardcoded list of characters
 * that doesn't include Romanian Ș/ș (U+0218/U+0219) and Ț/ț (U+021A/U+021B).
 *
 * We use transliterate() instead, which uses the full UTF-8 to ASCII conversion tables.
 */
class RomanianSlugger
{
    /**
     * Generate a URL-friendly slug from text with proper Romanian character handling.
     *
     * @param string $text The text to convert to a slug
     * @param string $separator The separator to use (default: '-')
     *
     * @return string The generated slug
     *
     * Examples:
     *   - "Știri Locale" -> "stiri-locale"
     *   - "Șeful Țării" -> "seful-tarii"
     *   - "Întâmplări din România" -> "intamplari-din-romania"
     */
    public function slugify(string $text, string $separator = '-'): string
    {
        // Use transliterate() instead of urlize() for proper Romanian support
        // transliterate() uses the full UTF-8 to ASCII conversion tables
        return Transliterator::transliterate($text, $separator);
    }

    /**
     * Static method for use as a callable (e.g., in Gedmo configuration).
     *
     * @param string $text The text to convert to a slug
     * @param string $separator The separator to use (default: '-')
     *
     * @return string The generated slug
     */
    public static function slugifyStatic(string $text, string $separator = '-'): string
    {
        return Transliterator::transliterate($text, $separator);
    }
}
