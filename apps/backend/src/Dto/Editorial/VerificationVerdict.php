<?php

declare(strict_types=1);

namespace App\Dto\Editorial;

use App\Enum\Editorial\VerdictType;

/**
 * Output of {@see \App\Service\Editorial\Verification\VerificationGate}
 * (Sprint 54 T54.9). Persisted alongside the graph snapshot on each signal
 * in the cluster (`source_signals.claim_graph_snapshot.verdict`).
 *
 * - `type`:              final verdict from the D3 publication matrix
 * - `reasoning`:         short RO-language explanation (rule id + details)
 * - `llmOverride`:       true when the LLM sanity check overrode the rule-based
 *                        verdict. Always logged explicit at `verification_llm_override`.
 * - `llmSanitySkipped`:  true when the LLM call failed and we fell back to the
 *                        rule-based verdict (fail-open).
 * - `escalationKeyword`: populated when Rule 0 triggers — "{family}:{keyword}"
 *                        so downstream observability can group by family.
 * - `confidence`:        0.0-1.0 — rule-based verdicts = 1.0, LLM-adjusted
 *                        reflects the LLM's own confidence.
 */
final readonly class VerificationVerdict
{
    public function __construct(
        public VerdictType $type,
        public string $reasoning,
        public bool $llmOverride = false,
        public bool $llmSanitySkipped = false,
        public ?string $escalationKeyword = null,
        public float $confidence = 1.0,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'verdict' => $this->type->value,
            'reasoning' => $this->reasoning,
            'llm_override' => $this->llmOverride,
            'llm_sanity_skipped' => $this->llmSanitySkipped,
            'escalation_keyword' => $this->escalationKeyword,
            'confidence' => $this->confidence,
        ];
    }
}
