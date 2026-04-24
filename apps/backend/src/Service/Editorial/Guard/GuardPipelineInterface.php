<?php

declare(strict_types=1);

namespace App\Service\Editorial\Guard;

use App\Entity\Article;

/**
 * Contract for the editorial guard pipeline (Sprint 55 T55.6).
 *
 * Exists so writer handlers can depend on the abstraction rather than the
 * concrete {@see GuardPipeline} — useful for testing and for S56 pipeline
 * variants (per-article-type guard sets).
 */
interface GuardPipelineInterface
{
    /**
     * @param array<string, mixed> $context
     */
    public function check(Article $article, array $context = []): GuardVerdict;
}
