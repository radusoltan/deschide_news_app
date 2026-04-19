<?php

declare(strict_types=1);

namespace App\Service\Editorial\Escalation;

use App\Entity\Topic;
use App\Enum\Editorial\EscalationCategory;
use App\Enum\LlmModelTier;
use App\Repository\AppSettingRepository;
use App\Service\Ai\Exception\LlmUnavailableException;
use App\Service\Ai\LlmRetryExecutor;
use App\Service\Ai\Provider\GeminiCliService;
use Psr\Log\LoggerInterface;

/**
 * Classifies verified-claim signals against the ADR-020 D7 taxonomy
 * (7 universal categories + 4 MD sensitivity families) — Sprint 55 T55.8.
 *
 * Triggered by {@see \App\MessageHandler\Editorial\VerifyClaimMessageHandler}
 * (T55.9) whenever the {@see \App\Service\Editorial\Verification\VerificationGate}
 * returns {@see \App\Enum\Editorial\VerdictType::ESCALATE_HUMAN}. The classifier
 * answers: "given this escalated claim, which taxonomic category gives the
 * editor the right mental model to judge it?"
 *
 * Fail-open: null return means "no high-confidence category found"; the
 * handler uses a generic family default and continues. Never throws.
 *
 * Confidence threshold is explicit: the LLM must return `is_escalation=true`
 * AND `confidence >= CONFIDENCE_THRESHOLD` (default 0.7). Below threshold →
 * null, logged as a low-confidence miss for Radu's follow-up tuning.
 */
class EscalationClassifier
{
    public const AGENT_ID = 'escalation_classifier';
    public const CONFIDENCE_THRESHOLD = 0.7;
    private const PRIMARY_TIER = LlmModelTier::HAIKU;
    private const FALLBACK_MODEL = 'gemini-2.5-flash';

    private const SYSTEM_PROMPT = <<<'PROMPT'
Ești clasificatorul editorial al redacției Deschide. Primești descrierea unei afirmații care a fost escaladată de sistemul de verificare și trebuie să o încadrezi în una dintre categoriile taxonomiei (ADR-020 D7).

Reguli stricte:
- Folosește DOAR criterii bazate pe ROL (șef de stat, patriarh, lider de formațiune, membru CEC), niciodată nume specifice.
- Returnează `is_escalation=true` doar dacă afirmația se încadrează clar într-una dintre cele 11 categorii.
- `confidence` reflectă claritatea încadrării (0.0 = necertă, 1.0 = evidentă).
- Dacă nu găsești o potrivire clară, returnează `is_escalation=false` cu `category="NONE"`.

Categoriile posibile (returnează exact unul dintre aceste coduri):
  CATEGORY_1_NUCLEAR_WAR              — act de război între state nucleare
  CATEGORY_2_HEAD_OF_STATE_DEATH      — deces șef de stat G20/UE sau patriarh ortodox
  CATEGORY_3_NBC_ATTACK               — atac nuclear/biologic/chimic
  CATEGORY_4_COUP                     — lovitură de stat (acțiune neconstituțională)
  CATEGORY_5_MASS_CASUALTIES          — peste 1000 de victime în primele 2 ore
  CATEGORY_6_CRIMINAL_ACCUSATION      — acuzații penale personalizate (risc defăimare)
  CATEGORY_7_PRE_CEC_ELECTORAL        — rezultate electorale anunțate înaintea CEC
  FAMILY_A_CHURCH                     — Biserică / Patriarhat
  FAMILY_B_EU_NATO_RUSSIA             — UE / NATO / Rusia în context moldovenesc
  FAMILY_C_TRANSNISTRIA_GAGAUZIA      — Transnistria / Găgăuzia
  FAMILY_D_CEC_PARTY_LEADERS          — CEC / lideri de partide

Formatul răspunsului: un singur obiect JSON strict (fără cod-fence, fără comentarii) cu schema:
{
  "category": "CATEGORY_X_...|FAMILY_Y_...|NONE",
  "is_escalation": bool,
  "confidence": float (0.0-1.0),
  "rationale": "string (1 frază, română)"
}
PROMPT;

    public function __construct(
        private readonly LlmRetryExecutor $llmRetryExecutor,
        private readonly GeminiCliService $geminiCliService,
        private readonly AppSettingRepository $appSettingRepository,
        private readonly LoggerInterface $logger,
    ) {}

    public function classify(
        string $claimText,
        string $primarySourceTitle,
        ?Topic $topic = null,
    ): ?EscalationCategory {
        if (!$this->appSettingRepository->getBool('agent.escalation_classifier.enabled', true)) {
            return null;
        }

        $userPrompt = $this->buildUserPrompt($claimText, $primarySourceTitle, $topic);

        try {
            $payload = $this->invokeLlm($userPrompt);
        } catch (\Throwable $e) {
            $this->logger->warning('escalation_classifier_llm_unavailable', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return $this->mapResult($payload);
    }

    private function buildUserPrompt(string $claimText, string $primarySourceTitle, ?Topic $topic): string
    {
        $roleTaxonomyVersion = $this->appSettingRepository->get(
            'editorial.escalation.role_taxonomy_version',
            'v1',
        );

        $topicLine = $topic !== null
            ? sprintf('Topic: %s', $topic->getTitle() ?? '(fără titlu)')
            : 'Topic: (nedetectat)';

        return sprintf(
            "Taxonomie versiune: %s\nSursă primară: %s\n%s\n\nClaim:\n%s\n\nClasifică claim-ul.",
            $roleTaxonomyVersion,
            $primarySourceTitle,
            $topicLine,
            $claimText,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function invokeLlm(string $userPrompt): array
    {
        try {
            $result = $this->llmRetryExecutor->executeWithRetry(
                agentId: self::AGENT_ID,
                messages: [['role' => 'user', 'content' => $userPrompt]],
                tier: self::PRIMARY_TIER,
                systemPrompt: self::SYSTEM_PROMPT,
            );

            return $this->decodeJson($result['content']);
        } catch (LlmUnavailableException $e) {
            $this->logger->warning('escalation_classifier_haiku_unavailable_trying_gemini', [
                'attempts' => $e->attempts,
            ]);
        }

        $geminiPrompt = self::SYSTEM_PROMPT . "\n\n" . $userPrompt;
        $raw = $this->geminiCliService->execute($geminiPrompt, [
            'model' => self::FALLBACK_MODEL,
            'timeout' => 60,
        ]);

        return $this->decodeJson($raw);
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
     * @param array<string, mixed> $payload
     */
    private function mapResult(array $payload): ?EscalationCategory
    {
        $isEscalation = (bool) ($payload['is_escalation'] ?? false);
        $confidence = (float) ($payload['confidence'] ?? 0.0);
        $categoryName = (string) ($payload['category'] ?? 'NONE');

        if (!$isEscalation) {
            return null;
        }
        if ($confidence < self::CONFIDENCE_THRESHOLD) {
            $this->logger->info('escalation_classifier_low_confidence', [
                'category' => $categoryName,
                'confidence' => $confidence,
                'threshold' => self::CONFIDENCE_THRESHOLD,
            ]);

            return null;
        }

        // Enum constants are backed by the dataset's expected_category values
        // (e.g. 'CATEGORY_1_NUCLEAR_WAR' → EscalationCategory::CATEGORY_1_NUCLEAR_WAR).
        foreach (EscalationCategory::cases() as $case) {
            if ($case->name === $categoryName) {
                return $case;
            }
        }

        $this->logger->warning('escalation_classifier_unknown_category', [
            'category' => $categoryName,
            'confidence' => $confidence,
        ]);

        return null;
    }
}
