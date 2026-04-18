<?php

declare(strict_types=1);

namespace App\Service\Ai\Exception;

/**
 * Claude CLI invocation failed with a pattern that is NOT safe to retry
 * (invalid_api_key, authentication errors, 400/404, unrecognized stderr,
 * or stdout parse failure). Throws through {@see \App\Service\Ai\LlmRetryExecutor}
 * without backoff.
 */
class ClaudeCliPermanentException extends \RuntimeException
{
}
