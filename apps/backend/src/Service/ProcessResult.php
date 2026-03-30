<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Value object returned by TranslationResultProcessor::process().
 */
final readonly class ProcessResult
{
    /**
     * @param string[] $savedLocales Locales that were successfully persisted
     * @param bool     $needsReview  True if any quality gate flagged the translation
     */
    public function __construct(
        public array $savedLocales,
        public bool $needsReview,
    ) {
    }
}
