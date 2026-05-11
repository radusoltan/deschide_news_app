<?php

declare(strict_types=1);

namespace App\Service\Ai\Exception;

use App\Enum\LlmModelTier;

/**
 * Thrown by {@see \App\Service\Ai\LlmRetryExecutor} when all retries on the
 * requested tier have exhausted. Per ADR-024 D3 (T57.P8), the downgrade-only
 * policy has been retired — there is no automatic fallback tier anymore; the
 * caller is expected to surface the failure via the `editorial_review_queue`
 * log line and let the exception propagate.
 *
 * `invocationId` is carried for telemetry correlation but is always `null`
 * on the exhaust path in P8 (the executor only opens {@see \App\Service\Ai\Logging\LlmInvocationLogger}
 * rows on successful invocations). Populating exhaust-path rows is a S58+
 * follow-up captured as cleanup debt.
 */
final class LlmUnavailableException extends \RuntimeException
{
    public function __construct(
        public readonly string $agentId,
        public readonly LlmModelTier $tier,
        public readonly int $attempts,
        public readonly ?string $invocationId = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'LLM agent "%s" exhausted %d attempts on tier %s. Marked for editorial review.',
                $agentId,
                $attempts,
                $tier->value,
            ),
            0,
            $previous,
        );
    }

    public function getInvocationId(): ?string
    {
        return $this->invocationId;
    }
}
