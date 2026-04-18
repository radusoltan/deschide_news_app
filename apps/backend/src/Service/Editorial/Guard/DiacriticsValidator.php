<?php

declare(strict_types=1);

namespace App\Service\Editorial\Guard;

/**
 * Flags Romanian-text diacritic violations: the project uses ONLY comma-below
 * letters ș (U+0219) and ț (U+021B). Cedilla variants ş (U+015F) and ț (U+0163)
 * are forbidden per hard rule 2 (Sprint 55 T55.6).
 *
 * Stateless, no LLM. Used by {@see StyleGuard} and directly in
 * {@see \App\Tests\Unit\Service\Editorial\Guard\DiacriticsValidatorTest}.
 */
class DiacriticsValidator
{
    /**
     * @return list<string> list of human-readable violation descriptions; empty when text is clean
     */
    public function validate(string $text): array
    {
        $violations = [];

        if (preg_match_all('/[ŞşŢţ]/u', $text, $matches, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($matches[0] as [$char, $byteOffset]) {
                // Convert byte offset to character offset so editors can
                // locate the violation in the source text.
                $charOffset = mb_strlen(substr($text, 0, $byteOffset));
                $expected = match ($char) {
                    'ş', 'Ş' => 'ș / Ș (comma-below)',
                    'ţ', 'Ţ' => 'ț / Ț (comma-below)',
                    default => 'comma-below variant',
                };
                $violations[] = sprintf(
                    "Cedilla '%s' at character offset %d — use %s.",
                    $char,
                    $charOffset,
                    $expected,
                );
            }
        }

        return $violations;
    }

    public function isValid(string $text): bool
    {
        return $this->validate($text) === [];
    }
}
