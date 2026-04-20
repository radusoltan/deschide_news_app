<?php

declare(strict_types=1);

namespace App\Service\Editorial\Guard;

use App\Entity\Article;
use App\Enum\LlmModelTier;
use App\Service\Ai\Exception\LlmUnavailableException;
use App\Service\Ai\LlmRetryExecutor;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Editorial\Llm\LlmInvocationLogger;
use Psr\Log\LoggerInterface;

/**
 * Editorial style guard (Sprint 55 T55.6, ADR-020 L4 layer).
 *
 * Two-stage check:
 *   1. {@see DiacriticsValidator} runs on title + lead + content. Any cedilla
 *      produces a failure (blocking — the article stays in status=NEW with
 *      a flag until an editor corrects the text).
 *   2. Haiku LLM check for stylistic rules (inverted pyramid, sentence length,
 *      passive voice discipline, attribution clarity). Risk rating in:
 *      low → warning-only, medium|high → failure.
 *
 * Audit D8: StyleGuard NEVER sets an escalationCode — style issues are never
 * grounds for ESCALATE_HUMAN, only editor review. Legal Category 6 is the
 * only escalation path (LegalGuard T55.7).
 *
 * LLM unavailability is fail-open: returns a pass-with-warning part so the
 * pipeline keeps flowing.
 */
class StyleGuard implements GuardInterface
{
    private const AGENT_ID = 'style_guard';
    private const PRIMARY_TIER = LlmModelTier::HAIKU;
    private const FALLBACK_MODEL = 'gemini-2.5-flash';

    private const SYSTEM_PROMPT = <<<'PROMPT'
Ești un redactor-șef al redacției Deschide. Evaluezi respectarea stilului editorial într-un articol publicat în limba română.

Criterii stricte:
1. Titlu în pirramidă inversată — esența evenimentului în primele 10 cuvinte.
2. Propoziții scurte — evită frazele de peste 25 de cuvinte.
3. Voce activă — vocea pasivă e acceptabilă când subiectul acțiunii e necunoscut.
4. Atribuire clară — afirmațiile neverificate independent trebuie atribuite explicit sursei.
5. Ton factual, fără exagerări, fără limbaj opinionativ.

Nu verifica diacriticele — un alt modul se ocupă de asta.

Formatul răspunsului: doar un obiect JSON strict (fără cod-fence) cu schema:
{
  "passed": bool,
  "issues": [
    {"rule": "inverted_pyramid|sentence_length|passive_voice|attribution|tone",
     "severity": "low|medium|high",
     "excerpt": "string",
     "rationale": "string"}
  ]
}
Dacă nu sunt probleme, întoarce {"passed": true, "issues": []}.
PROMPT;

    public function __construct(
        private readonly DiacriticsValidator $diacriticsValidator,
        private readonly LlmRetryExecutor $llmRetryExecutor,
        private readonly GeminiCliService $geminiCliService,
        private readonly LlmInvocationLogger $invocationLogger,
        private readonly LoggerInterface $logger,
    ) {}

    public function validate(Article $article, array $context = []): GuardVerdictPart
    {
        $failures = [];
        $warnings = [];

        // 1. Diacritics check (deterministic, always runs).
        $parts = [
            'title' => $article->getTitle() ?? '',
            'lead' => $article->getLead() ?? '',
            'content' => $article->getContent() ?? '',
        ];
        foreach ($parts as $field => $text) {
            foreach ($this->diacriticsValidator->validate($text) as $violation) {
                $failures[] = sprintf('[diacritics:%s] %s', $field, $violation);
            }
        }

        // 2. LLM style check (fail-open).
        try {
            $llmResult = $this->invokeLlm($article);
            foreach ($this->extractIssues($llmResult) as $issue) {
                $severity = $issue['severity'] ?? 'low';
                $rule = $issue['rule'] ?? 'unknown';
                $excerpt = $issue['excerpt'] ?? '';
                $rationale = $issue['rationale'] ?? '';
                $line = sprintf(
                    '[style:%s:%s] %s — „%s"',
                    $rule,
                    $severity,
                    $rationale,
                    mb_substr((string) $excerpt, 0, 80),
                );
                if ($severity === 'low') {
                    $warnings[] = $line;
                } else {
                    $failures[] = $line;
                }
            }
        } catch (\Throwable $e) {
            $this->logger->warning('style_guard_llm_skipped', [
                'article_id' => $article->getId(),
                'error' => $e->getMessage(),
            ]);
            $warnings[] = '[style] LLM check skipped (fail-open): ' . $e->getMessage();
        }

        return new GuardVerdictPart(
            failures: $failures,
            warnings: $warnings,
            escalationCode: null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function invokeLlm(Article $article): array
    {
        $userPrompt = $this->buildUserPrompt($article);

        try {
            $result = $this->llmRetryExecutor->executeWithRetry(
                agentId: self::AGENT_ID,
                messages: [['role' => 'user', 'content' => $userPrompt]],
                tier: self::PRIMARY_TIER,
                systemPrompt: self::SYSTEM_PROMPT,
            );

            $decoded = $this->decodeJson($result['content']);

            // T57.03 (ADR-023 D2) — attach verdict to executor-owned row.
            $invocationId = $result['invocation_id'] ?? null;
            if ($invocationId !== null) {
                $this->invocationLogger->attachVerdict(
                    $invocationId,
                    $this->mapVerdict($decoded),
                );
            }

            return $decoded;
        } catch (LlmUnavailableException $e) {
            $this->logger->warning('style_guard_haiku_unavailable_trying_gemini', [
                'attempts' => $e->attempts,
            ]);
        }

        // T57.03 — Gemini self-logs for cost parity with the Claude path.
        $geminiPrompt = self::SYSTEM_PROMPT . "\n\n" . $userPrompt;
        $geminiStart = (int) (microtime(true) * 1000);
        $raw = $this->geminiCliService->execute($geminiPrompt, [
            'model' => self::FALLBACK_MODEL,
            'timeout' => 60,
        ]);
        $geminiWallMs = (int) (microtime(true) * 1000) - $geminiStart;
        $decoded = $this->decodeJson($raw);

        $this->invocationLogger->logInvocation(
            agentName: self::AGENT_ID,
            promptHash: hash('xxh128', $geminiPrompt),
            durationMs: $geminiWallMs,
            inputTokens: 0,
            outputTokens: 0,
            model: self::FALLBACK_MODEL,
            verdict: $this->mapVerdict($decoded),
        );

        return $decoded;
    }

    /**
     * T57.03 — pass/block verdict derived from the LLM's issue severity list.
     * StyleGuard has no escalation ladder (per ADR-020 D8) so binary suffices.
     *
     * @param array<string, mixed> $decoded
     */
    private function mapVerdict(array $decoded): string
    {
        $issues = $decoded['issues'] ?? [];
        if (!is_array($issues)) {
            return 'pass';
        }
        foreach ($issues as $issue) {
            if (!is_array($issue)) {
                continue;
            }
            $severity = $issue['severity'] ?? 'low';
            if ($severity === 'medium' || $severity === 'high') {
                return 'block';
            }
        }

        return 'pass';
    }

    private function buildUserPrompt(Article $article): string
    {
        return sprintf(
            "Titlu: %s\n\nLead: %s\n\nCorp:\n%s",
            $article->getTitle() ?? '(fără titlu)',
            $article->getLead() ?? '(fără lead)',
            $article->getContent() ?? '(fără corp)',
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

    /**
     * @param array<string, mixed> $llmResult
     *
     * @return list<array<string, mixed>>
     */
    private function extractIssues(array $llmResult): array
    {
        $issues = $llmResult['issues'] ?? [];
        if (!is_array($issues)) {
            return [];
        }

        return array_values(array_filter($issues, static fn ($i): bool => is_array($i)));
    }
}
