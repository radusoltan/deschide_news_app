<?php

declare(strict_types=1);

namespace App\Service\Editorial\Guard;

use App\Agent\AgentDispatcher;
use App\Agent\Exception\EmergencyHaltException;
use App\Dto\Agent\AgentRequest;
use App\Entity\Article;
use App\Enum\LlmModelTier;
use App\Repository\AppSettingRepository;
use App\Service\Ai\Exception\LlmUnavailableException;
use App\Service\Editorial\Llm\LlmInvocationLogger;
use Psr\Log\LoggerInterface;

/**
 * Editorial legal guard (Sprint 55 T55.7, ADR-020 L4 layer).
 *
 * Scans articles for defamation risk, unattributed factual claims, and
 * speculation presented as fact. Routes by risk band (ADR-020 D7 / D8):
 *
 *   - {@see LegalCategoryDetector} flags the text as "Category 6" when it
 *     contains personalised criminal accusations (role-based accusation
 *     keywords + apparent person names within a narrow window). Category 6
 *     articles go through Sonnet (higher-quality legal reasoning); everything
 *     else goes through Haiku.
 *
 *   - LLM-returned severities map to outcomes per audit D8:
 *       · all `low`               → passed=true, warnings-only
 *       · any `medium`            → passed=false, failures (Article stays NEW,
 *                                    flagged for editor)
 *       · any `high` OR Cat6 hit  → passed=false, escalationCode='CATEGORY_6_CRIMINAL_ACCUSATION'
 *                                    (writer handler in T55.9 archives Article
 *                                    + opens EditorialEscalationLog row)
 *
 *   - LLM retry exhaust (post-ADR-024 D3 / T57.P8): hard fail.
 *     {@see LlmUnavailableException} is logged as `editorial_review_queue`
 *     and rethrown past {@see self::validate()}; there is no cross-provider
 *     fallback and no fail-open. Other unexpected throwables still flow
 *     through the legacy fail-closed-on-Cat6 / fail-open-on-general branch.
 */
class LegalGuard implements GuardInterface
{
    // TODO(s58-retro): rename AGENT_ID_GENERAL → AGENT_ID for naming consistency
    // with peer agents (flash_writer, style_guard, etc.) — deferred per T57.03
    // discovery to avoid mechanical diff churn during P' instrumentation sprint.
    private const AGENT_ID_GENERAL = 'legal_guard';
    public const ESCALATION_CODE_CATEGORY_6 = 'CATEGORY_6_CRIMINAL_ACCUSATION';
    public const ESCALATION_CODE_UNAVAILABLE = 'LEGAL_GUARD_UNAVAILABLE';

    private const SYSTEM_PROMPT = <<<'PROMPT'
Ești consilier juridic al redacției Deschide. Evaluezi un articol în limba română pentru riscuri juridice aplicabile presei din Republica Moldova (defăimare, acuzații penale nefundamentate, afirmații probabiliste prezentate ca fapte, invazie a vieții private, amenințare la adresa siguranței publice).

Reguli stricte:
- Nu comenta stilul sau diacriticele — un alt modul se ocupă de asta.
- Evaluează doar riscul juridic, nu corectitudinea factuală.
- Dacă articolul conține acuzații personalizate fără hotărâre judecătorească definitivă, riscul e cel puțin `high`.
- Dacă articolul conține speculații prezentate ca fapte, riscul este `medium`.
- Dacă articolul folosește un ton factual corect și atribuie clar toate afirmațiile, returnează passed=true cu issues=[].

Formatul răspunsului: doar un obiect JSON strict (fără cod-fence) cu schema:
{
  "passed": bool,
  "risks": [
    {"type": "defamation|unverified_claim|speculation|privacy|incitement",
     "severity": "low|medium|high",
     "excerpt": "string",
     "rationale": "string"}
  ]
}
PROMPT;

    public function __construct(
        private readonly LegalCategoryDetector $categoryDetector,
        private readonly AgentDispatcher $dispatcher,
        private readonly AppSettingRepository $appSettingRepository,
        private readonly LlmInvocationLogger $invocationLogger,
        private readonly LoggerInterface $logger,
    ) {}

    public function validate(Article $article, array $context = []): GuardVerdictPart
    {
        $scanText = ($article->getTitle() ?? '') . "\n\n"
            . ($article->getLead() ?? '') . "\n\n"
            . ($article->getContent() ?? '');

        $isCategory6 = $this->categoryDetector->isCategory6($scanText);
        $tier = $this->resolveTier($isCategory6);

        try {
            $llmResult = $this->invokeLlm($article, $tier, $isCategory6);
        } catch (EmergencyHaltException $e) {
            // ADR-024 D2 + T57.P2c.2 decision: halt is structurally different
            // from LLM unavailable (deliberate operator decision vs transient
            // infrastructure). Propagate up to the handler's defense-in-depth
            // silent-ACK terminal instead of falling through to the Cat6
            // fail-closed / general fail-open branches below. Without this
            // explicit catch, the generic \\Throwable clause below would
            // convert a halt into a CATEGORY_6 escalation for Cat6 articles
            // — silently swallowing the operator's halt signal.
            throw $e;
        } catch (LlmUnavailableException $e) {
            // ADR-024 D3 (T57.P8) — fail-closed on retry exhaust. Emit the
            // uniform editorial-review signal with entity context (article id
            // available at this layer, unlike the writers which are
            // pre-creation). Rethrow past the \\Throwable branch so Cat6
            // articles no longer get an auto ESCALATION_CODE_UNAVAILABLE
            // row — the message handler's top-level terminal owns the outcome.
            $this->logger->warning('editorial_review_queue', [
                'agent_id' => self::AGENT_ID_GENERAL,
                'entity_type' => 'article',
                'entity_id' => $article->getId(),
                'entity_refs' => [],
                'invocation_id' => $e->getInvocationId(),
                'tier_attempted' => $e->tier->value,
                'reason' => sprintf('%s_exhausted', $e->tier->value),
            ]);

            throw $e;
        } catch (\Throwable $e) {
            $this->logger->warning('legal_guard_llm_unavailable', [
                'article_id' => $article->getId(),
                'is_category_6' => $isCategory6,
                'error' => $e->getMessage(),
            ]);

            if ($isCategory6) {
                // Fail-closed — articles with apparent criminal accusations
                // must not bypass legal review even when the LLM is down.
                return new GuardVerdictPart(
                    failures: ['[legal] Category 6 pattern detected but LLM unavailable — escalating to human review.'],
                    warnings: [],
                    escalationCode: self::ESCALATION_CODE_UNAVAILABLE,
                );
            }

            // Fail-open for general articles.
            return new GuardVerdictPart(
                warnings: ['[legal] LLM check skipped (fail-open): ' . $e->getMessage()],
            );
        }

        return $this->mapResult($llmResult, $isCategory6);
    }

    private function resolveTier(bool $isCategory6): LlmModelTier
    {
        $key = $isCategory6
            ? 'agent.legal_guard.model_tier_categ6'
            : 'agent.legal_guard.model_tier_general';
        $value = $this->appSettingRepository->find($key)?->getValue();

        $tier = $value !== null ? LlmModelTier::tryFrom($value) : null;

        return $tier ?? ($isCategory6 ? LlmModelTier::SONNET : LlmModelTier::HAIKU);
    }

    /**
     * @return array<string, mixed>
     */
    private function invokeLlm(Article $article, LlmModelTier $tier, bool $isCategory6): array
    {
        $userPrompt = sprintf(
            "Titlu: %s\n\nLead: %s\n\nCorp:\n%s",
            $article->getTitle() ?? '(fără titlu)',
            $article->getLead() ?? '(fără lead)',
            $article->getContent() ?? '(fără corp)',
        );

        $response = $this->dispatcher->dispatch(new AgentRequest(
            agentId: self::AGENT_ID_GENERAL,
            messages: [['role' => 'user', 'content' => $userPrompt]],
            tier: $tier,
            systemPrompt: self::SYSTEM_PROMPT,
        ));

        $decoded = $this->decodeJson($response->content);

        // T57.03 (ADR-023 D2) — P' coverage. Attach the parsed verdict
        // to the executor-owned baseline row (T57.P2c.2: executor
        // writes the W' baseline transitively via the dispatcher;
        // invocation_id flows through AgentResponse DTO).
        if ($response->invocationId !== null) {
            $this->invocationLogger->attachVerdict(
                $response->invocationId,
                $this->mapVerdict($decoded, $isCategory6),
            );
        }

        return $decoded;
    }

    /**
     * T57.03 — discrete verdict string derived from the LLM's own severity
     * analysis. Matches what the pipeline will do with the article: category-6
     * escalation, medium-severity block, or pass.
     *
     * @param array<string, mixed> $decoded
     */
    private function mapVerdict(array $decoded, bool $isCategory6): string
    {
        $risks = $decoded['risks'] ?? [];
        $hasHigh = false;
        $hasMedium = false;
        if (is_array($risks)) {
            foreach ($risks as $risk) {
                if (!is_array($risk)) {
                    continue;
                }
                $severity = $risk['severity'] ?? 'low';
                if ($severity === 'high') {
                    $hasHigh = true;
                } elseif ($severity === 'medium') {
                    $hasMedium = true;
                }
            }
        }

        if ($isCategory6 || $hasHigh) {
            return 'escalate_cat6';
        }
        if ($hasMedium) {
            return 'block_medium';
        }

        return 'pass';
    }

    /**
     * @param array<string, mixed> $llmResult
     */
    private function mapResult(array $llmResult, bool $isCategory6): GuardVerdictPart
    {
        $risks = $llmResult['risks'] ?? [];
        if (!is_array($risks)) {
            $risks = [];
        }

        $failures = [];
        $warnings = [];
        $hasHigh = false;
        $hasMedium = false;

        foreach ($risks as $risk) {
            if (!is_array($risk)) {
                continue;
            }
            $severity = $risk['severity'] ?? 'low';
            $type = $risk['type'] ?? 'unknown';
            $excerpt = mb_substr((string) ($risk['excerpt'] ?? ''), 0, 100);
            $rationale = (string) ($risk['rationale'] ?? '');
            $line = sprintf(
                '[legal:%s:%s] %s — „%s"',
                $type,
                $severity,
                $rationale,
                $excerpt,
            );
            match ($severity) {
                'high' => $hasHigh = true,
                'medium' => $hasMedium = true,
                default => null,
            };
            if ($severity === 'low') {
                $warnings[] = $line;
            } else {
                $failures[] = $line;
            }
        }

        // Category 6 detection OR any high-severity risk triggers ESCALATE_HUMAN.
        if ($isCategory6 || $hasHigh) {
            return new GuardVerdictPart(
                failures: $failures !== [] ? $failures : ['[legal] Category 6 pattern flagged by detector.'],
                warnings: $warnings,
                escalationCode: self::ESCALATION_CODE_CATEGORY_6,
            );
        }

        // Medium severity → blocking failure, no escalation.
        if ($hasMedium) {
            return new GuardVerdictPart(
                failures: $failures,
                warnings: $warnings,
            );
        }

        // All-low or no issues → pass with warnings only.
        return new GuardVerdictPart(
            warnings: $warnings,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJson(string $raw): array
    {
        $trimmed = trim($raw);
        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $trimmed) ?? $trimmed;
        }
        $open = strpos($trimmed, '{');
        $close = strrpos($trimmed, '}');
        if ($open !== false && $close !== false && $close > $open) {
            $trimmed = substr($trimmed, $open, $close - $open + 1);
        }

        /** @var array<string, mixed> $decoded */
        $decoded = json_decode($trimmed, true, 32, JSON_THROW_ON_ERROR);

        return $decoded;
    }
}
