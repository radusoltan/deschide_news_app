<?php

declare(strict_types=1);

namespace App\Service\Ai\Exception;

use App\Enum\LlmModelTier;

/**
 * Thrown by {@see \App\Service\Ai\LlmRetryExecutor} when all retries on the
 * primary tier have exhausted. Carries the fallback tier (if configured) so
 * callers can decide whether to degrade gracefully — actual fallback rerouting
 * lands in Sprint 56 per ADR-020 D5.
 */
final class LlmUnavailableException extends \RuntimeException
{
    public function __construct(
        public readonly string $agentId,
        public readonly LlmModelTier $tier,
        public readonly ?LlmModelTier $fallbackTier,
        public readonly int $attempts,
        ?\Throwable $previous = null,
    ) {
        $fallbackLabel = $fallbackTier === null ? 'none' : $fallbackTier->value;

        parent::__construct(
            sprintf(
                'LLM agent "%s" exhausted %d attempts on tier %s. Fallback tier: %s '
                . '(rerouting deferred to Sprint 56).',
                $agentId,
                $attempts,
                $tier->value,
                $fallbackLabel,
            ),
            0,
            $previous,
        );
    }

    public function isFallbackDetected(): bool
    {
        return $this->fallbackTier !== null;
    }
}
