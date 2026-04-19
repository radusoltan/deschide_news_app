<?php

declare(strict_types=1);

namespace App\Service\Editorial\Guard;

/**
 * Heuristic pre-filter for ADR-020 D7 Category 6 content — "personalised
 * criminal accusations" (Sprint 55 T55.7). No LLM.
 *
 * The detector's single job is to answer: should {@see LegalGuard} route
 * the article through the expensive Sonnet tier (isCategory6=true) or the
 * cheap Haiku tier (false)?
 *
 * Category 6 indicators:
 *   1. Accusation-language keywords in Romanian (acuzat de, învinuit de,
 *      suspectat de, abuz, traficant, criminal, ...).
 *   2. Presence of a specific person (proxy via capitalised multi-word
 *      patterns — "NER-lite"). Role-only references ("un deputat", "un fost
 *      ministru") don't trip the second indicator because they aren't
 *      capitalised after lowercasing title-case.
 *
 * Decision rule: the text belongs to Category 6 when at least 2 distinct
 * indicators land within any 50-word sliding window. A single keyword or a
 * single capitalised name alone does NOT trigger — we want conjunction.
 *
 * Returns a pure boolean — rationale and match excerpts stay inside the
 * service's internal scratch space (not exposed yet; can be surfaced when a
 * future review UI asks for them).
 */
class LegalCategoryDetector
{
    /** @var list<string> case-insensitive Romanian accusation-language markers */
    private const ACCUSATION_KEYWORDS = [
        'acuzat de',
        'acuzată de',
        'acuzați de',
        'acuzate de',
        'învinuit de',
        'învinuită de',
        'învinuiți de',
        'învinuite de',
        'suspect de corupție',
        'suspectat de',
        'suspectată de',
        'abuz sexual',
        'abuzat sexual',
        'violator',
        'traficant de',
        'spălare de bani',
        'luare de mită',
        'dare de mită',
        'criminal',
        'ucigaș',
        'criminal organizat',
    ];

    private const WINDOW_WORDS = 50;
    private const MIN_INDICATORS = 2;

    /**
     * Zero-width + non-breaking-space chars an adversary RSS can splice between
     * letters of "Popescu" or "acuzat de" so the literal mb_strpos / capitalised-
     * word scan misses the indicator. We strip them before any matching while
     * keeping the caller's original `$text` untouched (logging / storage use it).
     */
    private const INVISIBLE_BYPASS_CHARS = '/[\x{200B}-\x{200D}\x{FEFF}\x{00A0}]/u';

    public function isCategory6(string $text): bool
    {
        // Unicode normalisation against bypass vectors — combining diacritics
        // (NFKC folds "é" = e + U+0301 into single codepoint) + zero-width strip.
        $normalized = \Normalizer::isNormalized($text, \Normalizer::FORM_KC)
            ? $text
            : (\Normalizer::normalize($text, \Normalizer::FORM_KC) ?: $text);
        $stripped = preg_replace(self::INVISIBLE_BYPASS_CHARS, '', $normalized) ?? $normalized;

        $lower = mb_strtolower($stripped);

        $words = preg_split('/\s+/u', $lower, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (\count($words) === 0) {
            return false;
        }

        // 1. Collect keyword-match word positions.
        $keywordPositions = [];
        foreach (self::ACCUSATION_KEYWORDS as $keyword) {
            $pos = 0;
            while (($found = mb_strpos($lower, $keyword, $pos)) !== false) {
                // Translate char offset → word index.
                $wordIdx = mb_substr_count(mb_substr($lower, 0, $found), ' ');
                $keywordPositions[] = $wordIdx;
                $pos = $found + mb_strlen($keyword);
            }
        }

        if (\count($keywordPositions) === 0) {
            return false;
        }

        // 2. NER-lite: find word positions that look like proper-noun runs in
        //    the ORIGINAL-case text (lowercase view would miss capitalisation).
        //    Uses the stripped/normalised variant so splits like "Po\u{200B}pescu"
        //    are rejoined before capitalisation + bigram detection.
        $originalWords = preg_split('/\s+/u', $stripped, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $namePositions = [];
        for ($i = 0; $i < \count($originalWords) - 1; ++$i) {
            if ($this->isCapitalisedWord($originalWords[$i]) && $this->isCapitalisedWord($originalWords[$i + 1])) {
                $namePositions[] = $i;
            }
        }

        // 3. Sliding-window join: is there any WINDOW_WORDS window containing
        //    at least MIN_INDICATORS distinct indicator positions (keyword OR name)?
        $allPositions = array_values(array_unique([...$keywordPositions, ...$namePositions]));
        sort($allPositions);

        $n = \count($allPositions);
        for ($i = 0; $i < $n; ++$i) {
            $windowEnd = $allPositions[$i] + self::WINDOW_WORDS;
            $count = 0;
            for ($j = $i; $j < $n && $allPositions[$j] <= $windowEnd; ++$j) {
                ++$count;
                if ($count >= self::MIN_INDICATORS) {
                    return true;
                }
            }
        }

        return false;
    }

    private function isCapitalisedWord(string $word): bool
    {
        // Strip common Romanian punctuation attached to word tokens.
        $trimmed = trim($word, ',.;:!?„"”\'"()[]—–-');
        if ($trimmed === '') {
            return false;
        }

        $first = mb_substr($trimmed, 0, 1);

        // First char must be an uppercase letter (Latin or Romanian-specific).
        if (preg_match('/^[A-ZȘȚĂÎÂ]/u', $first) !== 1) {
            return false;
        }

        // Filter out pure-uppercase abbreviations (CEC, NATO, UE) — those are
        // not person names. Require at least one lowercase char past the first.
        if (mb_strlen($trimmed) < 2) {
            return false;
        }
        $rest = mb_substr($trimmed, 1);

        return preg_match('/[a-zșțăîâ]/u', $rest) === 1;
    }
}
