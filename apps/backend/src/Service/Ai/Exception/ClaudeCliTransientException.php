<?php

declare(strict_types=1);

namespace App\Service\Ai\Exception;

/**
 * Claude CLI invocation failed with a pattern that is safe to retry
 * (rate_limit, 529, overload, overloaded, timeout). Surfaced by
 * {@see \App\Service\Ai\ClaudeCliClient} and caught by
 * {@see \App\Service\Ai\LlmRetryExecutor} to trigger exponential backoff.
 */
class ClaudeCliTransientException extends \RuntimeException
{
}
