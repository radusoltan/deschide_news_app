<?php

declare(strict_types=1);

namespace App\Entity\Editorial;

use App\Repository\Editorial\LlmAgentCallLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Per-invocation log of LLM agent calls (Sprint 56 T56.09, ADR-022 D2 promotion).
 *
 * Replaces the S55 grep-based observability path for {@see \App\Command\Editorial\LlmCostSummaryCommand}.
 * Each row captures a single LLM call's wire metrics — token counts, duration,
 * cost — so the extended smoke (T56.12) can query aggregates via SQL instead
 * of parsing rotated log files.
 *
 * Near-immutable: only `verdict` can be set post-construction via
 * {@see self::setVerdict()} (T57.03 `attachVerdict` flow, ADR-023 D2).
 * All other properties remain readonly after the initial insert.
 *
 * Rationale for relaxing the invariant on one field: a gate's verdict is
 * knowable only after the LLM response has been parsed — downstream of the
 * executor-owned baseline write. Rather than defer the entire row until the
 * agent completes parsing (which would lose observability on LLM calls whose
 * parsing crashes), we persist the row at the end of the wire call and
 * UPDATE the verdict column once the agent decides. Retention policy is an
 * operational decision tracked as ADR-022 Open Question #2.
 *
 * Storage discipline:
 *   - `prompt_hash` is a SHA-256 hex digest of the full prompt, NOT the
 *     prompt content itself. Used for dedup detection across invocations
 *     (same prompt = same hash = retry detection). Prompt text storage is
 *     explicitly out of scope per ADR-022 to avoid PII + size growth.
 *   - `invocation_id` is a ULID string (26 chars). Carried through any
 *     downstream log lines that reference this call so analysts can join
 *     across Monolog channels.
 *   - Token counts default to 0 when the LLM wrapper does not expose them
 *     (Gemini CLI fallback path). 0 is a sentinel, not a metric — analytics
 *     must treat rows with both input and output = 0 as "metrics missing"
 *     rather than "free call."
 *   - `verdict` is the agent-level classification when the agent acts as
 *     a gate (legal_guard, style_guard, escalation_classifier). Null for
 *     writers (flash_writer, developing_story_writer) that produce content
 *     rather than decisions.
 *
 * Indexes:
 *   - (agent_name, created_at): drives the default time-range per-agent
 *     aggregation query used by the CLI.
 *   - (verdict): powers dashboard metric aggregation without a table scan
 *     once verdict-producing agents hook in (deferred agents: legal_guard,
 *     style_guard, escalation_classifier — T56.09 ships with flash_writer only).
 */
#[ORM\Entity(repositoryClass: LlmAgentCallLogRepository::class)]
#[ORM\Table(name: 'llm_agent_call_log')]
#[ORM\Index(name: 'idx_llm_call_agent_created', columns: ['agent_name', 'created_at'])]
#[ORM\Index(name: 'idx_llm_call_verdict', columns: ['verdict'])]
#[ORM\UniqueConstraint(name: 'uniq_llm_call_invocation_id', columns: ['invocation_id'])]
class LlmAgentCallLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $agentName;

    #[ORM\Column(length: 26)]
    private string $invocationId;

    #[ORM\Column(length: 64)]
    private string $promptHash;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $model;

    #[ORM\Column(type: Types::INTEGER)]
    private int $durationMs;

    #[ORM\Column(type: Types::INTEGER)]
    private int $inputTokenCount;

    #[ORM\Column(type: Types::INTEGER)]
    private int $outputTokenCount;

    #[ORM\Column(type: Types::INTEGER)]
    private int $cacheReadTokenCount;

    #[ORM\Column(type: Types::INTEGER)]
    private int $cacheCreationTokenCount;

    /** Cost in USD as recorded by the LLM wrapper. Stored as float; aggregations round at render time. */
    #[ORM\Column(type: Types::FLOAT)]
    private float $costUsd;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $verdict;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        string $agentName,
        string $invocationId,
        string $promptHash,
        int $durationMs,
        int $inputTokenCount,
        int $outputTokenCount,
        int $cacheReadTokenCount = 0,
        int $cacheCreationTokenCount = 0,
        float $costUsd = 0.0,
        ?string $model = null,
        ?string $verdict = null,
        ?\DateTimeImmutable $createdAt = null,
    ) {
        $this->agentName = $agentName;
        $this->invocationId = $invocationId;
        $this->promptHash = $promptHash;
        $this->durationMs = $durationMs;
        $this->inputTokenCount = $inputTokenCount;
        $this->outputTokenCount = $outputTokenCount;
        $this->cacheReadTokenCount = $cacheReadTokenCount;
        $this->cacheCreationTokenCount = $cacheCreationTokenCount;
        $this->costUsd = $costUsd;
        $this->model = $model;
        $this->verdict = $verdict;
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAgentName(): string
    {
        return $this->agentName;
    }

    public function getInvocationId(): string
    {
        return $this->invocationId;
    }

    public function getPromptHash(): string
    {
        return $this->promptHash;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function getDurationMs(): int
    {
        return $this->durationMs;
    }

    public function getInputTokenCount(): int
    {
        return $this->inputTokenCount;
    }

    public function getOutputTokenCount(): int
    {
        return $this->outputTokenCount;
    }

    public function getCacheReadTokenCount(): int
    {
        return $this->cacheReadTokenCount;
    }

    public function getCacheCreationTokenCount(): int
    {
        return $this->cacheCreationTokenCount;
    }

    public function getCostUsd(): float
    {
        return $this->costUsd;
    }

    public function getVerdict(): ?string
    {
        return $this->verdict;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * T57.03 (ADR-023 D2) — sole setter on this entity. Called by
     * {@see \App\Service\Editorial\Llm\LlmInvocationLogger::attachVerdict()}
     * once a gate finishes parsing the LLM response.
     */
    public function setVerdict(string $verdict): void
    {
        $this->verdict = $verdict;
    }
}
