<?php

declare(strict_types=1);

namespace App\Service\Scraping;

use App\Repository\ArticleRepository;

final readonly class ContentDeduplicator
{
    public function __construct(
        private ArticleRepository $articleRepository,
    ) {}

    /**
     * Check if content with this hash already exists.
     */
    public function isDuplicate(string $bodyText): bool
    {
        $hash = $this->computeHash($bodyText);

        return $this->articleRepository->findOneBy(['contentHash' => $hash]) !== null;
    }

    /**
     * Compute SHA-256 hash of normalized content.
     */
    public function computeHash(string $bodyText): string
    {
        $normalized = $this->normalizeForHash($bodyText);

        return hash('sha256', $normalized);
    }

    /**
     * Normalize text for consistent hashing:
     * - lowercase
     * - strip excessive whitespace
     * - remove punctuation
     * - UTF-8 NFC normalize
     */
    private function normalizeForHash(string $text): string
    {
        // UTF-8 NFC normalization
        if (\extension_loaded('intl')) {
            $normalized = \Normalizer::normalize($text, \Normalizer::FORM_C);
            if ($normalized !== false) {
                $text = $normalized;
            }
        }

        // Lowercase
        $text = mb_strtolower($text);

        // Remove punctuation (keep letters, digits, whitespace)
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text);

        // Collapse whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}
