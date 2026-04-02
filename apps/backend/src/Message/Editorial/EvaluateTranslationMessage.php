<?php

declare(strict_types=1);

namespace App\Message\Editorial;

/**
 * Dispatched after translation to evaluate and optimize quality.
 */
final readonly class EvaluateTranslationMessage
{
    public function __construct(
        public int $articleId,
        public string $targetLang,
        public int $maxIterations = 2,
    ) {}
}
