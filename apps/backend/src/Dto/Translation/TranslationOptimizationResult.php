<?php

declare(strict_types=1);

namespace App\Dto\Translation;

final readonly class TranslationOptimizationResult
{
    /**
     * @param int    $articleId   Article that was evaluated
     * @param string $targetLang  Target language (en, ru)
     * @param float  $initialScore Score before optimization
     * @param float  $finalScore   Score after optimization
     * @param int    $iterations   Number of evaluate-optimize cycles run
     * @param string $finalStatus  'complete' or 'needs_review'
     */
    public function __construct(
        public int $articleId,
        public string $targetLang,
        public float $initialScore,
        public float $finalScore,
        public int $iterations,
        public string $finalStatus,
    ) {}
}
