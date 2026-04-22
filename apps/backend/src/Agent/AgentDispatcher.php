<?php

declare(strict_types=1);

namespace App\Agent;

use App\Agent\Exception\EmergencyHaltException;
use App\Dto\Agent\AgentRequest;
use App\Dto\Agent\AgentResponse;
use App\Repository\AppSettingRepository;
use App\Service\Ai\LlmRetryExecutor;
use Psr\Log\LoggerInterface;

/**
 * Unified entry point for agent LLM invocations (ADR-024 D2).
 *
 * The dispatcher is deliberately **mechanical, not smart** (ADR-024 Q2):
 * callers resolve their own tier (via {@see \App\Service\Ai\TierResolver}
 * or agent-semantic logic such as LegalGuard's Category-6 direct
 * AppSettings read) and pass it explicitly on the {@see AgentRequest}.
 * This preserves agent-layer policy where it belongs and keeps the
 * dispatcher as a thin pipe.
 *
 * Responsibilities kept inside the dispatcher:
 *  1. Pre-LLM `editorial.emergency_halt` circuit breaker — generalizes
 *     the [[ADR-022]] D5 silent-ACK pattern (previously hardcoded in 5
 *     MessageHandlers) to every agent call. Raises
 *     {@see EmergencyHaltException} so non-handler callers (CLI
 *     commands, tests, ad-hoc service invocations) surface the halt
 *     loudly. MessageHandlers retain their own pre-existing fast-path
 *     check — they silent-ACK before the dispatcher is ever
 *     constructed, so they never observe this exception.
 *  2. Array → typed-DTO conversion around the {@see LlmRetryExecutor}
 *     return value. Executor continues to own the actual Claude CLI
 *     invocation, 4-attempt backoff, and `LlmAgentCallLog` baseline
 *     row (T57.03 W' via {@see \App\Service\Editorial\Llm\LlmInvocationLogger}).
 *
 * Responsibilities explicitly NOT kept inside the dispatcher:
 *  - Tier resolution (agents resolve and pass explicit — ADR-024 Q2).
 *  - `LlmAgentCallLog` row open/close (delegated to
 *    {@see LlmRetryExecutor}; dispatcher does not reopen log rows —
 *    ADR-024 Q4. A dispatcher-level log would double-count invocations).
 *  - Gemini fallback branch (stays agent-level through P2 per ADR-024
 *    Q3; removed in T57.P8 when the downgrade-only policy retires).
 *  - Mercure broadcast on agent lifecycle (ADR-024 D2 marks this
 *    optional and disabled by default; not wired in P2).
 *
 * Coverage: once all 9 editorial agents are migrated (T57.P2b + P2c
 * sub-tasks), `LlmAgentCallLog` reaches 100% coverage of the editorial
 * pipeline transitively through the dispatcher→executor chain. Support
 * pipeline (briefing, topic classifier, translator) migrates in
 * T57.P4–P7.
 */
final readonly class AgentDispatcher
{
    private const EMERGENCY_HALT_KEY = 'editorial.emergency_halt';

    public function __construct(
        private LlmRetryExecutor $executor,
        private AppSettingRepository $appSettings,
        private LoggerInterface $logger,
    ) {}

    /**
     * Dispatch an agent request through the editorial LLM pipeline.
     *
     * @throws EmergencyHaltException when `editorial.emergency_halt` is `true`
     * @throws \App\Service\Ai\Exception\LlmUnavailableException when retries exhaust
     * @throws \App\Service\Ai\Exception\ClaudeCliPermanentException on permanent transport errors
     */
    public function dispatch(AgentRequest $request): AgentResponse
    {
        if ($this->appSettings->getBool(self::EMERGENCY_HALT_KEY, false)) {
            $this->logger->info('agent_dispatcher.emergency_halt', [
                'agent_id' => $request->agentId,
                'tier' => $request->tier->value,
            ]);

            throw new EmergencyHaltException($request->agentId);
        }

        $result = $this->executor->executeWithRetry(
            agentId: $request->agentId,
            messages: $request->messages,
            tier: $request->tier,
            systemPrompt: $request->systemPrompt,
        );

        return new AgentResponse(
            content: $result['content'],
            agentId: $result['agent_id'],
            tier: $request->tier,
            model: $result['model'],
            attempts: $result['attempts'],
            invocationId: $result['invocation_id'] ?? null,
            metrics: $result['metrics'] ?? null,
        );
    }
}
