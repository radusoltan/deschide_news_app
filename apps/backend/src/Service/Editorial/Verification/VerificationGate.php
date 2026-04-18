<?php

declare(strict_types=1);

namespace App\Service\Editorial\Verification;

use App\Dto\Editorial\ClaimOriginGraph;
use App\Dto\Editorial\VerificationVerdict;
use App\Entity\Editorial\SourceSignal;
use App\Enum\Editorial\VerdictType;
use App\Enum\LlmModelTier;
use App\Repository\AppSettingRepository;
use App\Service\Ai\LlmRetryExecutor;
use App\Service\Ai\TierResolver;
use Psr\Log\LoggerInterface;

/**
 * The D3 publication-matrix gate (Sprint 54 T54.9, ADR-020 D3).
 *
 * Rule-based primary, LLM sanity secondary:
 *
 *   Rule 0 — escalation keyword in title/summary → ESCALATE_HUMAN
 *            (unless the signal is a clearly-attributed declaration, in which
 *            case we downgrade to FLASH_WITH_ATTRIBUTION so direct quotes from
 *            politicians don't trip the nuclear-attack detector).
 *   Rule 1 — 1 chain + ≥1 tier-1 node                       → FLASH_WITH_ATTRIBUTION
 *   Rule 2 — 2+ chains + ≥1 tier-1 + same alignment          → FLASH_WITH_ASSERTION_YELLOW
 *   Rule 3 — 2+ chains + ≥1 tier-1 + alignment-diverse       → FULL_FLASH
 *   else                                                     → REJECT
 *
 * LLM sanity check:
 *   - Tier routing: FLASH_WITH_ASSERTION_YELLOW (same-alignment + multi-chain,
 *     conflict-prone) routes to Sonnet via `model_tier_conflict`; everything
 *     else uses Haiku via `model_tier_simple`.
 *   - The LLM receives the graph metrics + verdict and responds with
 *     {"verdict_sound": bool, "confidence": float, "reasoning": string}.
 *   - Override rule: the LLM can override the rule-based verdict only when
 *     verdict_sound=false AND confidence >= 0.8 AND it supplies an alternative
 *     verdict value from the enum. Any override is logged explicit at
 *     `verification_llm_override`.
 *   - Fail-open: transport error or non-JSON response leaves the rule-based
 *     verdict in place; we log `verification_llm_sanity_skipped`.
 *
 * Rule 0 bypasses the LLM entirely (no Sonnet cost on obvious high-stakes),
 * which is also the reason Rule 0 + declaration-vs-assertion heuristic live
 * inside this service and not as a separate EscalationClassifier — keeping
 * the bypass and its downgrade heuristic on the same page.
 */
class VerificationGate
{
    public const AGENT_ID = 'verification_gate';

    /**
     * Grouped escalation keyword patterns. Keys are "family" labels used for
     * observability tagging; values are lowercase substring patterns that
     * match against `title . ' ' . raw_summary` for each signal.
     *
     * Sprint 55+ `EditorialEscalationClassifier` replaces the whole lot with
     * an LLM call; the literal patterns here are an S54 placeholder to keep
     * the high-stakes gate active before that classifier lands.
     *
     * @var array<string, list<string>>
     */
    private const ESCALATION_PATTERNS = [
        // Global high-stakes (ADR-020 D7 cross-cutting).
        'war_adjacent' => [
            'nuclear', 'nuclear strike', 'nuclear weapon', 'război nuclear',
            'missile strike', 'missile attack', 'invaded', 'invasion of',
        ],
        'head_of_state_death' => [
            'president dies', 'president killed', 'moartea președintelui',
            'putin died', 'putin killed', 'zelensky killed', 'trump assassinat',
        ],
        'coup' => [
            'coup d\'état', 'military coup', 'overthrow', 'lovitură de stat',
            'arrested president',
        ],
        'mass_casualties' => [
            'thousands killed', 'mii de morți', 'catastrophic casualties',
            'mass casualty',
        ],
        // MD-specific Family C — Transnistria / Gagauzia military context.
        'md_separatist_military' => [
            'transnistria troops', 'transnistria armată', 'transnistria atac',
            'transnistria forțe', 'găgăuzia troops', 'găgăuzia armată',
            'găgăuzia atac',
        ],
        // MD Family D — election integrity. Both bare and Romanian
        // definite-article forms because RO newswire writes both.
        'md_election_integrity' => [
            'alegeri anulate', 'alegerile anulate', 'alegerile au fost anulate',
            'alegeri trucate', 'alegerile trucate', 'alegerile au fost trucate',
            'alegeri suspendate', 'alegerile suspendate',
            'cec exclus', 'cec invalidat', 'scrutin anulat', 'scrutinul anulat',
        ],
        // MD Family A — Church / Metropolia.
        'md_church' => [
            'excluderea mitropoliei', 'suspendarea mitropoliei',
            'kirill excomunicare', 'mitropolia moldovei suspendată',
        ],
        // MD Family B — EU / NATO rupture.
        'md_eu_nato_rupture' => [
            'suspendarea aderării', 'ruptură diplomatică',
            'rappel ambasador', 'retragere nato',
        ],
    ];

    /**
     * High-stakes topic words used by the NotebookLM hook (T54.10) to flag
     * YELLOW verdicts for fact-check double-check. These are broader than
     * {@see self::ESCALATION_PATTERNS}: they name sensitive topics without
     * requiring action-verb co-occurrence. A signal mentioning
     * "Transnistria" alone doesn't trip Rule 0 (needs "+ armată/atac") but
     * still warrants NotebookLM verification when coverage is
     * same-alignment-only.
     *
     * @var list<string>
     */
    private const HIGH_STAKES_TOPIC_WORDS = [
        'transnistria', 'găgăuzia', 'gagauzia',
        'alegeri', 'alegerile', 'scrutin', 'scrutinul',
        'mitropolia', 'kirill', 'ortodox', 'biserica',
        'aderare', 'aderării', 'uniunea europeană',
        'nato', 'rusia', 'kremlin',
        'nuclear', 'sancțiuni', 'sancțiunilor',
    ];

    public function __construct(
        private readonly LlmRetryExecutor $executor,
        private readonly TierResolver $tierResolver,
        private readonly AppSettingRepository $appSettings,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param list<SourceSignal> $signals all signals in the confirmed cluster
     */
    public function rule(ClaimOriginGraph $graph, array $signals): VerificationVerdict
    {
        $verdict = $this->computeVerdict($graph, $signals);
        $this->emitNotebookLmHookIfApplicable($graph, $verdict, $signals);

        return $verdict;
    }

    /**
     * @param list<SourceSignal> $signals
     */
    private function computeVerdict(ClaimOriginGraph $graph, array $signals): VerificationVerdict
    {
        $escalationMatch = $this->matchEscalationKeyword($signals);
        if ($escalationMatch !== null) {
            if ($this->isAttributedDeclaration($signals)) {
                $this->logger->info('verification_escalation_downgrade', [
                    'keyword' => $escalationMatch,
                    'reason' => 'attributed_declaration',
                    'topic_hash' => $graph->topicHash,
                ]);

                return new VerificationVerdict(
                    type: VerdictType::FLASH_WITH_ATTRIBUTION,
                    reasoning: sprintf(
                        'Keyword escalate-ar fi declanșat (%s) dar semnalul este declarație atribuită explicit — downgrade la flash cu atribuire.',
                        $escalationMatch,
                    ),
                    escalationKeyword: $escalationMatch,
                );
            }

            $this->logger->info('verification_escalation_keyword_match', [
                'keyword' => $escalationMatch,
                'topic_hash' => $graph->topicHash,
            ]);

            return new VerificationVerdict(
                type: VerdictType::ESCALATE_HUMAN,
                reasoning: sprintf('Escalation keyword match: %s', $escalationMatch),
                escalationKeyword: $escalationMatch,
            );
        }

        $ruleVerdict = $this->applyD3Matrix($graph);
        $ruleReasoning = $this->reasoningFor($ruleVerdict, $graph);

        if (!$this->tierResolver->isEnabled(self::AGENT_ID)) {
            return new VerificationVerdict(
                type: $ruleVerdict,
                reasoning: $ruleReasoning,
                llmSanitySkipped: true,
            );
        }

        return $this->llmSanityCheck($graph, $ruleVerdict, $ruleReasoning);
    }

    /**
     * Sprint 54 T54.10 — log-only hook. When the feature flag
     * `notebooklm.factcheck.enabled` is on and the verdict is high-stakes
     * (ESCALATE_HUMAN, or FLASH_WITH_ASSERTION_YELLOW + escalation keyword),
     * emit a structured log line `verification_notebooklm_would_invoke` that
     * names the claim and the reason. Sprint 55+ will swap the log for an
     * actual {@see \App\Service\NotebookLM\NotebookLmFactCheckServiceInterface}
     * call once we have the Article/Topic context plumbed through the
     * verification layer (the existing interface requires both, neither of
     * which exist at this point in the signal pipeline).
     *
     * FULL_FLASH is intentionally NOT high-stakes — alignment-diversity +
     * rule-based already provide sufficient safety; NotebookLM cost would
     * be pure overhead.
     *
     * @param list<SourceSignal> $signals
     */
    private function emitNotebookLmHookIfApplicable(
        ClaimOriginGraph $graph,
        VerificationVerdict $verdict,
        array $signals,
    ): void {
        if (!$this->appSettings->getBool('notebooklm.factcheck.enabled', false)) {
            return;
        }

        $reason = $this->determineHighStakesReason($verdict, $signals);
        if ($reason === null) {
            return;
        }

        $primarySignal = $signals[0] ?? null;
        $claim = $primarySignal !== null ? trim($primarySignal->getTitle()) : '(unknown)';

        $this->logger->info('verification_notebooklm_would_invoke', [
            'topic_hash' => $graph->topicHash,
            'claim_hash' => $graph->claimHash,
            'verdict_before_notebooklm' => $verdict->type->value,
            'high_stakes_reason' => $reason,
            'claim' => mb_substr($claim, 0, 200),
        ]);
    }

    /**
     * Returns a stable string label when the verdict is high-stakes, else
     * null. Labels are observable-grouped so downstream metrics can split
     * "escalate_verdict" from "yellow_with_keyword:*" cost/frequency.
     *
     * @param list<SourceSignal> $signals
     */
    private function determineHighStakesReason(
        VerificationVerdict $verdict,
        array $signals,
    ): ?string {
        if ($verdict->type === VerdictType::ESCALATE_HUMAN) {
            return 'escalate_verdict';
        }

        if ($verdict->type === VerdictType::FLASH_WITH_ASSERTION_YELLOW) {
            $topic = $this->matchHighStakesTopic($signals);
            if ($topic !== null) {
                return 'yellow_with_topic:' . $topic;
            }
        }

        return null;
    }

    /**
     * Broader-than-escalation topic match used exclusively by the
     * NotebookLM hook. Returns the first matched HIGH_STAKES_TOPIC_WORDS
     * pattern (lowercase, substring) or null.
     *
     * @param list<SourceSignal> $signals
     */
    private function matchHighStakesTopic(array $signals): ?string
    {
        foreach ($signals as $signal) {
            $haystack = mb_strtolower(
                $signal->getTitle() . ' ' . ($signal->getRawSummary() ?? ''),
            );
            foreach (self::HIGH_STAKES_TOPIC_WORDS as $word) {
                if (str_contains($haystack, $word)) {
                    return $word;
                }
            }
        }

        return null;
    }

    private function applyD3Matrix(ClaimOriginGraph $graph): VerdictType
    {
        $tier1Count = $graph->getTierCount(1);
        $chains = $graph->independentChains;
        $alignmentDiverse = $graph->hasAlignmentDiversity();

        if ($chains === 1 && $tier1Count >= 1) {
            return VerdictType::FLASH_WITH_ATTRIBUTION;
        }
        if ($chains >= 2 && $tier1Count >= 1 && !$alignmentDiverse) {
            return VerdictType::FLASH_WITH_ASSERTION_YELLOW;
        }
        if ($chains >= 2 && $tier1Count >= 1 && $alignmentDiverse) {
            return VerdictType::FULL_FLASH;
        }

        return VerdictType::REJECT;
    }

    private function reasoningFor(VerdictType $verdict, ClaimOriginGraph $graph): string
    {
        $tier1 = $graph->getTierCount(1);

        return match ($verdict) {
            VerdictType::FLASH_WITH_ATTRIBUTION => sprintf(
                'Rule 1: 1 lanț independent, %d surse tier-1 — flash cu atribuire.',
                $tier1,
            ),
            VerdictType::FLASH_WITH_ASSERTION_YELLOW => sprintf(
                'Rule 2: %d lanțuri independente, %d tier-1, dar aliniere editorială unică (%s) — flash cu flag asertiv galben.',
                $graph->independentChains,
                $tier1,
                implode(', ', $graph->alignmentClusters),
            ),
            VerdictType::FULL_FLASH => sprintf(
                'Rule 3: %d lanțuri independente, %d tier-1, alinieri diverse (%s) — flash complet.',
                $graph->independentChains,
                $tier1,
                implode(', ', $graph->alignmentClusters),
            ),
            VerdictType::REJECT => sprintf(
                'Coroborare insuficientă: %d lanțuri, %d tier-1. Semnalul este arhivat pentru audit.',
                $graph->independentChains,
                $tier1,
            ),
            VerdictType::ESCALATE_HUMAN => 'Escalat spre revizuire umană.',
        };
    }

    /**
     * @param list<SourceSignal> $signals
     */
    private function matchEscalationKeyword(array $signals): ?string
    {
        foreach ($signals as $signal) {
            $haystack = mb_strtolower(
                $signal->getTitle() . ' ' . ($signal->getRawSummary() ?? ''),
            );
            foreach (self::ESCALATION_PATTERNS as $family => $patterns) {
                foreach ($patterns as $pattern) {
                    if (str_contains($haystack, $pattern)) {
                        return sprintf('%s:%s', $family, $pattern);
                    }
                }
            }
        }

        return null;
    }

    /**
     * Attributed-declaration heuristic (Sprint 54 placeholder; S55 replaces
     * with LLM-based EditorialEscalationClassifier).
     *
     * A signal is an attributed declaration when its title matches the
     * pattern "NUME: «…»" or "NUME: \"…\"" — the colon-plus-quoted-text
     * shape that newswire titles use for politician statements. Case
     * matters: the name must start capitalized.
     *
     * @param list<SourceSignal> $signals
     */
    private function isAttributedDeclaration(array $signals): bool
    {
        foreach ($signals as $signal) {
            $title = $signal->getTitle();
            // e.g. 'Dodon: "Alegerile au fost trucate"' or 'Putin: «...»'
            if (preg_match('/^[\p{Lu}][\p{L}\s\.\-]{1,60}:\s*[«"\']/u', $title) === 1) {
                return true;
            }
        }

        return false;
    }

    private function llmSanityCheck(
        ClaimOriginGraph $graph,
        VerdictType $ruleVerdict,
        string $ruleReasoning,
    ): VerificationVerdict {
        $tier = $this->resolveTierForVerdict($ruleVerdict);

        $messages = [[
            'role' => 'user',
            'content' => $this->buildSanityPrompt($graph, $ruleVerdict, $ruleReasoning),
        ]];

        try {
            $response = $this->executor->executeWithRetry(
                self::AGENT_ID,
                $messages,
                $tier,
                $this->getSanitySystemPrompt(),
            );
        } catch (\Throwable $e) {
            $this->logger->warning('verification_llm_sanity_skipped', [
                'topic_hash' => $graph->topicHash,
                'rule_verdict' => $ruleVerdict->value,
                'tier' => $tier->value,
                'error' => $e->getMessage(),
            ]);

            return new VerificationVerdict(
                type: $ruleVerdict,
                reasoning: $ruleReasoning,
                llmSanitySkipped: true,
            );
        }

        $sanity = $this->parseSanityResponse($response['content']);
        $sound = (bool) ($sanity['verdict_sound'] ?? true);
        $confidence = (float) ($sanity['confidence'] ?? 0.5);
        $alternative = \is_string($sanity['alternative_verdict'] ?? null)
            ? VerdictType::tryFrom($sanity['alternative_verdict'])
            : null;

        $overrideConfidenceFloor = (float) $this->appSettings->get(
            'editorial.verification.llm_override_confidence',
            '0.8',
        );

        if (!$sound && $alternative !== null && $confidence >= $overrideConfidenceFloor) {
            // Downgrade-only policy (ADR-020 D1, "AI proposes, editor decides"):
            // the LLM may only tighten the verdict, never relax it. Upgrade
            // attempts are rejected and the rule-based verdict is kept.
            if ($alternative->rank() > $ruleVerdict->rank()) {
                $this->logger->info('verification_llm_upgrade_rejected', [
                    'topic_hash' => $graph->topicHash,
                    'rule_verdict' => $ruleVerdict->value,
                    'attempted_upgrade_to' => $alternative->value,
                    'confidence' => $confidence,
                    'reasoning' => mb_substr((string) ($sanity['reasoning'] ?? ''), 0, 200),
                ]);
            } else {
                $this->logger->info('verification_llm_override', [
                    'topic_hash' => $graph->topicHash,
                    'rule_verdict' => $ruleVerdict->value,
                    'overridden_to' => $alternative->value,
                    'confidence' => $confidence,
                    'reasoning' => mb_substr((string) ($sanity['reasoning'] ?? ''), 0, 200),
                ]);

                return new VerificationVerdict(
                    type: $alternative,
                    reasoning: sprintf(
                        'LLM override (%s, conf=%.2f): %s',
                        $tier->value,
                        $confidence,
                        (string) ($sanity['reasoning'] ?? ''),
                    ),
                    llmOverride: true,
                    confidence: $confidence,
                );
            }
        }

        return new VerificationVerdict(
            type: $ruleVerdict,
            reasoning: $ruleReasoning,
            confidence: $confidence,
        );
    }

    private function resolveTierForVerdict(VerdictType $ruleVerdict): LlmModelTier
    {
        $variant = $ruleVerdict === VerdictType::FLASH_WITH_ASSERTION_YELLOW
            ? 'model_tier_conflict'
            : 'model_tier_simple';

        return $this->tierResolver->resolve(self::AGENT_ID, $variant);
    }

    private function getSanitySystemPrompt(): string
    {
        return <<<'PROMPT'
Ești un analist editorial care verifică decizia unui sistem automat de gate pentru publicare.

Primești: un graf al lanțurilor de surse (independent_chains, alignment_clusters, tier_distribution) și un verdict propus din matricea D3.

Răspunzi STRICT în format JSON fără text înainte sau după:
{"verdict_sound": true|false, "confidence": 0.0-1.0, "reasoning": "scurtă justificare", "alternative_verdict": "flash_with_attribution"|"flash_with_assertion_yellow"|"full_flash"|"escalate_human"|"reject"|null}

REGULI:
- Acceptă verdictul doar dacă e coerent cu datele grafului.
- Override permis doar când graful arată clar altceva decât verdictul propus și poți nume verdictul corect.
- "confidence" reflectă cât de sigur ești; override se aplică doar la confidence ≥ 0.8.
- Diacritice românești cu virgulă dedesubt (ș U+0219, ț U+021B).
- Răspunde NUMAI cu JSON valid. Fără backticks, fără comentarii.
PROMPT;
    }

    private function buildSanityPrompt(
        ClaimOriginGraph $graph,
        VerdictType $ruleVerdict,
        string $ruleReasoning,
    ): string {
        return sprintf(
            "Graf:\nindependent_chains: %d\nalignment_clusters: [%s]\ntier_distribution: %s\nnoduri_inregistrate: %d\nmuchii: %d\n\nVerdict propus: %s\nRaționament: %s\n\nEste verdictul coerent cu graful?",
            $graph->independentChains,
            implode(', ', $graph->alignmentClusters),
            json_encode($graph->tierDistribution, \JSON_UNESCAPED_UNICODE),
            $graph->registeredNodeCount(),
            \count($graph->edges),
            $ruleVerdict->value,
            $ruleReasoning,
        );
    }

    /**
     * @return array{verdict_sound?: bool, confidence?: float, reasoning?: string, alternative_verdict?: string|null}
     */
    private function parseSanityResponse(string $content): array
    {
        $trimmed = trim($content);
        if (str_starts_with($trimmed, '```')) {
            $trimmed = (string) preg_replace('/^```[a-zA-Z]*\r?\n/', '', $trimmed);
            $trimmed = (string) preg_replace('/\r?\n```\s*$/', '', $trimmed);
        }

        $decoded = json_decode($trimmed, true);
        if (!\is_array($decoded)) {
            $this->logger->warning('VerificationGate: LLM sanity returned non-JSON, treating as sound', [
                'preview' => mb_substr($content, 0, 200),
            ]);

            return ['verdict_sound' => true, 'confidence' => 0.0];
        }

        /** @var array{verdict_sound?: bool, confidence?: float, reasoning?: string, alternative_verdict?: string|null} $decoded */
        return $decoded;
    }
}
