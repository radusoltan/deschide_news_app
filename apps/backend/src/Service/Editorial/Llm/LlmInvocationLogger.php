<?php

declare(strict_types=1);

namespace App\Service\Editorial\Llm;

use App\Entity\Editorial\LlmAgentCallLog;
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
 * Hooked into {@see \App\Service\Editorial\Writer\FlashWriter} in T56.09 as
 * single-agent end-to-end verification. Other agents (developing_story_writer,
 * legal_guard, style_guard, escalation_classifier) are follow-up scope — the
 * minimal-hook discipline keeps the T56.09 change surface small enough to
 * land before the T56.12 extended smoke window.
 */
readonly class LlmInvocationLogger
{
    public function __construct(
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {}

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
    ): void {
        try {
            $entry = new LlmAgentCallLog(
                agentName: $agentName,
                invocationId: (string) new Ulid(),
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
        } catch (\Throwable $e) {
            // Observability MUST NOT break the pipeline. Caller already has
            // the LLM response; whether we can record its metrics is a
            // separate concern.
            $this->logger->warning('llm_invocation_logger.persist_failed', [
                'agent_name' => $agentName,
                'prompt_hash' => $promptHash,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
