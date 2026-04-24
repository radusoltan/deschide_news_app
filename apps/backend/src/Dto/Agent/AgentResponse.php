<?php

declare(strict_types=1);

namespace App\Dto\Agent;

use App\Enum\LlmModelTier;

/**
 * Output DTO returned by {@see \App\Agent\AgentDispatcher::dispatch()}.
 *
 * Mirrors the shape of the array returned by
 * {@see \App\Service\Ai\LlmRetryExecutor::executeWithRetry()} (T57.03 W'
 * baseline) but wrapped as a typed, immutable DTO so agent consumers can
 * address fields as properties instead of array keys.
 *
 * Field semantics:
 *  - `content`:        raw Claude CLI response body (JSON string, to be
 *                      parsed by the agent per its own output schema).
 *  - `agentId`:        echo of the request's `agentId` — useful in tests and
 *                      log correlation.
 *  - `tier`:           echo of the resolved tier the executor actually ran.
 *  - `model`:          concrete model string (`claude-haiku-4-5-20251001`,
 *                      ...) derived from {@see LlmModelTier::toModelString()}.
 *  - `attempts`:       number of attempts the executor consumed (1 on happy
 *                      path, up to 4 on retries).
 *  - `invocationId`:   ULID of the LlmAgentCallLog row opened by the
 *                      executor. `null` when persistence failed — callers
 *                      with a post-parse verdict should then skip
 *                      {@see \App\Service\Editorial\Llm\LlmInvocationLogger::attachVerdict()}.
 *  - `metrics`:        raw Claude CLI token/cost envelope, copied through
 *                      for cost observability. Kept as `?array` in P2 for
 *                      pragmatic reasons (converting to a typed sub-DTO is
 *                      deferred to T57.P8 cleanup alongside Gemini branch
 *                      consolidation).
 */
final readonly class AgentResponse
{
    /**
     * @param array<string, mixed>|null $metrics
     */
    public function __construct(
        public string $content,
        public string $agentId,
        public LlmModelTier $tier,
        public string $model,
        public int $attempts,
        public ?string $invocationId,
        public ?array $metrics = null,
    ) {}
}
