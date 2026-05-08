<?php

declare(strict_types=1);

namespace App\Service\Ai\Logging;

use App\Entity\Ai\LlmAgentCallLog;
use App\Repository\Ai\LlmAgentCallLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Ulid;

/**
 * Persists a row per LLM invocation to `llm_agent_call_log` for the
 * DB-backed observability path introduced in T56.09 (ADR-022 D2 promotion).
 *
 * Contract:
 *   - Each call persists one row and flushes. One round-trip per invocation
 *     is deliberate — LLM call durations are 1–10s, a <5ms DB flush is
 *     negligible overhead and buys atomic observability (no "logger
 *     crashed before flush" surprises in the extended smoke).
 *   - Graceful degrade: persistence failures are caught, logged at WARNING,
 *     and swallowed. Observability MUST NOT break the pipeline — an LLM call
 *     that ran successfully but whose log row couldn't be written must
 *     still ship the Article to downstream handlers.
 *   - ULID per invocation is generated inside the logger. Callers pass the
 *     prompt hash and metrics only; the invocation id never needs to travel
 *     across service boundaries (the DB is the source of truth).
 *
 * T57.03 (ADR-023 D2) extended the contract:
 *   - `logInvocation()` now returns the generated ULID so callers can thread
 *     it into their return value and gates can later call `attachVerdict()`
 *     with it. Returns `null` on persist failure — the W' baseline row
 *     couldn't be written, and post-hoc verdict attach would have nothing
 *     to UPDATE.
 *   - `attachVerdict()` UPDATEs the verdict column on an existing row. Used
 *     by gate agents (LegalGuard / StyleGuard / EscalationClassifier /
 *     VerificationGate) after they parse the LLM response and map it to a
 *     discrete verdict string. Fire-and-forget; warns but never throws.
 *
 * Hooked into {@see \App\Service\Ai\LlmRetryExecutor} universally in T57.03.
 * Gemini fallback paths in FlashWriter / LegalGuard / StyleGuard /
 * EscalationClassifier still call `logInvocation()` directly because the
 * executor only routes Claude CLI (per S54 charter).
 */
readonly class LlmInvocationLogger
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
        private LlmAgentCallLogRepository $repository,
    ) {}

    /**
     * @return string|null the generated ULID on success; null on persist failure.
     */
    public function logInvocation(
        string $agentName,
        string $promptHash,
        int $durationMs,
        int $inputTokens,
        int $outputTokens,
        int $cacheReadTokens = 0,
        int $cacheCreationTokens = 0,
        float $costUsd = 0.0,
        ?string $model = null,
        ?string $verdict = null,
    ): ?string {
        $invocationId = (string) new Ulid();

        try {
            $entry = new LlmAgentCallLog(
                agentName: $agentName,
                invocationId: $invocationId,
                promptHash: $promptHash,
                durationMs: $durationMs,
                inputTokenCount: $inputTokens,
                outputTokenCount: $outputTokens,
                cacheReadTokenCount: $cacheReadTokens,
                cacheCreationTokenCount: $cacheCreationTokens,
                costUsd: $costUsd,
                model: $model,
                verdict: $verdict,
            );

            $this->em->persist($entry);
            $this->em->flush();

            return $invocationId;
        } catch (\Throwable $e) {
            // Observability MUST NOT break the pipeline. Caller already has
            // the LLM response; whether we can record its metrics is a
            // separate concern.
            $this->logger->warning('llm_invocation_logger.persist_failed', [
                'agent_name' => $agentName,
                'prompt_hash' => $promptHash,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * T57.03 — UPDATE the verdict column on a previously-logged invocation.
     * Called by gate agents post-parse. Graceful degrade: warns but never
     * throws, and no-ops if the row doesn't exist (e.g., baseline write
     * failed earlier in the same pipeline).
     */
    public function attachVerdict(string $invocationId, string $verdict): void
    {
        try {
            $log = $this->repository->findOneBy(['invocationId' => $invocationId]);

            if ($log === null) {
                $this->logger->warning('llm_invocation_logger.attach_verdict_no_row', [
                    'invocation_id' => $invocationId,
                    'verdict' => $verdict,
                ]);

                return;
            }

            $log->setVerdict($verdict);
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->logger->warning('llm_invocation_logger.attach_verdict_failed', [
                'invocation_id' => $invocationId,
                'verdict' => $verdict,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
