<?php

declare(strict_types=1);

namespace App\Agent;

use App\Agent\Exception\EmergencyHaltException;
use App\Dto\Agent\AgentRequest;
use App\Dto\Agent\AgentResponse;
use App\Repository\AppSettingRepository;
use App\Service\Ai\LlmRetryExecutor;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Ai\Logging\LlmInvocationLogger;
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
 *  1. Pre-LLM `agent.emergency_halt` circuit breaker — generalizes
 *     the [[ADR-022]] D5 silent-ACK pattern (previously hardcoded in 5
 *     MessageHandlers) to every agent call. Raises
 *     {@see EmergencyHaltException} so non-handler callers (CLI
 *     commands, tests, ad-hoc service invocations) surface the halt
 *     loudly. MessageHandlers retain their own pre-existing fast-path
 *     check — they silent-ACK before the dispatcher is ever
 *     constructed, so they never observe this exception.
 *  2. Transport routing — dispatch branches on
 *     {@see \App\Enum\LlmModelTier::transport()} post halt-check:
 *       - `claude_cli` → {@see LlmRetryExecutor} (per-S54 charter; owns
 *         4-attempt backoff, {@see LlmInvocationLogger} T57.03 W' baseline row).
 *       - `gemini_cli` → {@see GeminiCliService} directly (T57.P7.C1;
 *         dispatcher opens its own `LlmAgentCallLog` row since the
 *         executor rejects non-`claude_cli` tiers by construction).
 *     The claude_cli path still owns its logging — dispatcher never
 *     double-logs claude calls (ADR-024 Q4 preserved for that branch).
 *
 * Responsibilities explicitly NOT kept inside the dispatcher:
 *  - Tier resolution (agents resolve and pass explicit — ADR-024 Q2).
 *  - Gemini retry/backoff — T57.P7 translator contract is "skip + manual
 *    flag" per ADR-024 D1, so {@see \App\Service\Ai\Provider\GeminiCliException}
 *    rethrows verbatim to the caller who decides per-locale semantics.
 *  - Mercure broadcast on agent lifecycle (ADR-024 D2 marks this
 *    optional and disabled by default; not wired in P2).
 *
 * Coverage: claude_cli branch reaches 100% `LlmAgentCallLog` coverage
 * transitively through executor→invocation-logger. gemini_cli branch
 * closes the previously-bypassed translator path in T57.P7.C2 by
 * migrating {@see \App\MessageHandler\TranslateArticleHandler} to use
 * this dispatcher; C1 ships the infrastructure standalone.
 */
readonly class AgentDispatcher
{
    private const EMERGENCY_HALT_KEY = 'agent.emergency_halt';

    /**
     * Short generic argv directive for Gemini-transport dispatch. Translator-
     * specific instructions live in the agent-file (loaded by the caller and
     * passed through {@see AgentRequest::$systemPrompt}), which the dispatcher
     * concatenates into stdin per the Plan-P7 A1 mapping.
     */
    private const GEMINI_ARGV_PROMPT = 'Translate following the instructions. Return JSON.';

    /** Default Gemini timeout when `agent.{id}.timeout_seconds` is absent. */
    private const GEMINI_TIMEOUT_FALLBACK_SECONDS = 300;

    /** Canonical Gemini model name reported to {@see AgentResponse::$model}. */
    private const GEMINI_MODEL = 'gemini-2.5-flash';

    public function __construct(
        private LlmRetryExecutor $executor,
        private AppSettingRepository $appSettings,
        private LoggerInterface $logger,
        private GeminiCliService $geminiCli,
        private LlmInvocationLogger $invocationLogger,
        private string $projectDir,
    ) {}

    /**
     * Dispatch an agent request through the editorial LLM pipeline.
     *
     * @throws EmergencyHaltException when `agent.emergency_halt` is `true`
     * @throws \App\Service\Ai\Exception\LlmUnavailableException when claude_cli retries exhaust
     * @throws \App\Service\Ai\Exception\ClaudeCliPermanentException on permanent transport errors (claude_cli)
     * @throws \App\Service\Ai\Provider\GeminiCliException on Gemini subprocess failure/timeout (gemini_cli)
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

        if ($request->tier->transport() === 'gemini_cli') {
            return $this->dispatchViaGemini($request);
        }

        return $this->dispatchViaClaude($request);
    }

    private function dispatchViaClaude(AgentRequest $request): AgentResponse
    {
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

    /**
     * Gemini-transport branch (T57.P7.C1).
     *
     * A1 sub-mapping (Plan-P7 decision 4):
     *  - {@see AgentRequest::$systemPrompt} → stdin preamble (agent-file contents)
     *  - `messages[0].content`              → stdin body (user input, e.g., article JSON)
     *  - Dispatcher argv                    → {@see self::GEMINI_ARGV_PROMPT}
     *  - `jsonOutput: true`                 → hardcoded for uniform dispatcher contract
     *  - `cwd`                              → `$projectDir` so Gemini resolves `.gemini/agents/*`
     *  - `timeout`                          → `agent.{id}.timeout_seconds` AppSetting (default 300)
     *
     * Observability: one `LlmAgentCallLog` row per successful Gemini call. Token/
     * cost metrics left at 0 in C1 per T1 decision — `GeminiCliService` already
     * writes a richer `gemini_call` log line with per-call token counts and USD
     * cost from the CLI envelope, so the audit trail is preserved without
     * duplicating extraction logic here.
     */
    private function dispatchViaGemini(AgentRequest $request): AgentResponse
    {
        $timeout = $this->appSettings->getInt(
            \sprintf('agent.%s.timeout_seconds', $request->agentId),
            self::GEMINI_TIMEOUT_FALLBACK_SECONDS,
        );

        $userContent = $request->messages[0]['content'] ?? '';
        $stdin = ($request->systemPrompt ?? '') . "\n\n---\n\n" . $userContent;

        $startedAt = microtime(true);

        $output = $this->geminiCli->execute(
            self::GEMINI_ARGV_PROMPT,
            [
                'stdin' => $stdin,
                'jsonOutput' => true,
                'timeout' => $timeout,
                'cwd' => $this->projectDir,
            ],
        );

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        $invocationId = $this->invocationLogger->logInvocation(
            agentName: $request->agentId,
            promptHash: hash('xxh128', $stdin),
            durationMs: $durationMs,
            inputTokens: 0,
            outputTokens: 0,
            costUsd: 0.0,
            model: self::GEMINI_MODEL,
            verdict: null,
        );

        return new AgentResponse(
            content: $output,
            agentId: $request->agentId,
            tier: $request->tier,
            model: self::GEMINI_MODEL,
            attempts: 1,
            invocationId: $invocationId,
            metrics: null,
        );
    }
}
