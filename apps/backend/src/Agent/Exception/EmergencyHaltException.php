<?php

declare(strict_types=1);

namespace App\Agent\Exception;

/**
 * Thrown by {@see \App\Agent\AgentDispatcher::dispatch()} when the
 * `agent.emergency_halt` AppSetting is `true` at dispatch time.
 *
 * ADR-024 D2 generalizes the [[ADR-022]] D5 silent-ACK circuit breaker
 * (previously hardcoded in 5 MessageHandlers) to every agent invocation
 * that flows through the dispatcher. The two layers compose as
 * defense-in-depth:
 *
 *  - Handler-level check (pre-existing, unchanged in P2): fast-path
 *    silent-ACK before the dispatcher is even constructed. Logs
 *    `emergency_halt.triggered` and returns `null` from the handler.
 *    Handlers never see this exception because their own check exits
 *    first.
 *
 *  - Dispatcher-level check (introduced in this commit): catches all
 *    non-handler callers — CLI commands, integration tests, future
 *    service invocations — that would otherwise bypass the pipeline
 *    circuit breaker. Raises this exception so the halt surfaces
 *    loudly in those ad-hoc paths (silent-ACK would mask bugs).
 *
 * Lives in {@see \App\Agent\Exception} (policy & orchestration concern)
 * rather than {@see \App\Service\Ai\Exception} (transport & retry
 * concern — `LlmUnavailableException`, `ClaudeCliTransientException`).
 */
final class EmergencyHaltException extends \RuntimeException
{
    public function __construct(
        public readonly string $agentId,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'agent.emergency_halt is active — dispatch for agent "%s" refused.',
                $agentId,
            ),
            previous: $previous,
        );
    }
}
