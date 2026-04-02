<?php

declare(strict_types=1);

namespace App\Dto\Translation;

final readonly class TranslationEvaluationResult
{
    /**
     * @param float       $score                0.0–1.0 quality score
     * @param list<array{type: string, severity: string, description: string}> $issues
     * @param bool        $needsRevision        True if score < threshold or critical/major issues found
     * @param string|null $revisionInstructions  Instructions for the optimizer to fix issues
     */
    public function __construct(
        public float $score,
        public array $issues,
        public bool $needsRevision,
        public ?string $revisionInstructions,
    ) {}
}
