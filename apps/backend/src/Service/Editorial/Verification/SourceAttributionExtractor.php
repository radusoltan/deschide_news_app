<?php

declare(strict_types=1);

namespace App\Service\Editorial\Verification;

use App\Dto\Editorial\SourceAttributionResult;
use App\Entity\Editorial\SourceSignal;
use App\Service\Ai\LlmRetryExecutor;
use App\Service\Ai\TierResolver;
use Psr\Log\LoggerInterface;

/**
 * First LLM call of the editorial verification layer (Sprint 54 T54.7,
 * ADR-020 D2/D5). Given a raw {@see SourceSignal}, asks the configured
 * tier (Haiku 4.5 by default) to extract two structured fields:
 *
 *   - `source_attribution`: textual mentions of the claim origin
 *     ("potrivit Reuters", "surse diplomatice", "raportul Bellingcat")
 *   - `source_links_out`: absolute external URLs named in the body
 *
 * Both feed the {@see \App\DTO\Editorial\ClaimOriginGraph} built by
 * {@see SignalAggregator} (T54.8), which needs "does this signal carry its
 * own citations?" to distinguish a primary-source chain from an echo.
 *
 * Failure contract is fail-open: transport errors, malformed JSON, or
 * ambiguous content all resolve to {@see SourceAttributionResult::empty()}
 * so the downstream stabilization + aggregation pipeline continues. The
 * agent enabled/disabled flag is NOT evaluated here — the caller (the
 * handler) checks {@see TierResolver::isEnabled()} and bypasses this
 * service when the agent is off.
 */
class SourceAttributionExtractor
{
    public const AGENT_ID = 'source_attribution';

    public function __construct(
        private readonly LlmRetryExecutor $executor,
        private readonly TierResolver $tierResolver,
        private readonly LoggerInterface $logger,
    ) {}

    public function extract(SourceSignal $signal): SourceAttributionResult
    {
        $tier = $this->tierResolver->resolve(self::AGENT_ID);
        $messages = [[
            'role' => 'user',
            'content' => $this->buildUserPrompt($signal),
        ]];

        try {
            $response = $this->executor->executeWithRetry(
                self::AGENT_ID,
                $messages,
                $tier,
                $this->getSystemPrompt(),
            );
        } catch (\Throwable $e) {
            $this->logger->warning('SourceAttributionExtractor: LLM call failed, fail-open', [
                'source_signal_id' => $signal->getId(),
                'tier' => $tier->value,
                'error' => $e->getMessage(),
            ]);

            return SourceAttributionResult::empty();
        }

        return $this->parseResponse($response['content'], $signal);
    }

    /**
     * System prompt — Haiku understands Romanian natively and handles the
     * diacritic-correct output requirement directly. Keep fixed to improve
     * Anthropic prompt-caching hit rate (first Haiku call in this codebase,
     * warms the cache for subsequent signals in the same window).
     */
    private function getSystemPrompt(): string
    {
        return <<<'PROMPT'
Ești un analist editorial. Extragi informații despre surse dintr-un titlu și rezumat de știre pentru redacția Deschide.md.

Răspunzi STRICT în format JSON cu două câmpuri, fără text înainte sau după:
- "source_attribution" (string sau null): mențiuni textuale explicite de surse ca "potrivit Reuters", "surse diplomatice", "un oficial al administrației", "raportul Bellingcat". Returnează null dacă nu există astfel de mențiuni în text.
- "source_links_out" (array de string-uri): URL-uri externe absolute (http/https) menționate în text care trimit către surse primare sau terțe. EXCLUDE: reclame, social media share, navigation, linkuri către site-ul propriu al outletului. Returnează [] dacă nu există linkuri relevante.

REGULI:
- Diacritice românești corecte cu virgulă dedesubt (ș U+0219, ț U+021B), niciodată varianta cu cedilă.
- Nu fabrica source_attribution — dacă textul nu menționează o sursă, returnează null.
- Răspunde NUMAI cu JSON valid. Fără backticks, fără comentarii, fără explicații.

Exemplu valid:
{"source_attribution":"potrivit agenției Reuters","source_links_out":["https://www.reuters.com/article/xyz"]}

Exemplu fără surse:
{"source_attribution":null,"source_links_out":[]}
PROMPT;
    }

    private function buildUserPrompt(SourceSignal $signal): string
    {
        $summary = $signal->getRawSummary();
        $summaryText = ($summary !== null && trim($summary) !== '') ? trim($summary) : '(fără rezumat)';

        $canonical = $signal->getCanonicalUrl() ?? $signal->getSourceUrl();

        return sprintf(
            "Titlu:\n%s\n\nRezumat:\n%s\n\nURL canonic:\n%s\n\nExtrage source_attribution și source_links_out ca JSON.",
            $signal->getTitle(),
            $summaryText,
            $canonical,
        );
    }

    private function parseResponse(string $content, SourceSignal $signal): SourceAttributionResult
    {
        $stripped = $this->stripMarkdownFences(trim($content));

        $parsed = json_decode($stripped, true);
        if (!\is_array($parsed)) {
            $this->logger->warning('SourceAttributionExtractor: non-JSON response, fail-open', [
                'source_signal_id' => $signal->getId(),
                'preview' => mb_substr($content, 0, 200),
            ]);

            return SourceAttributionResult::empty();
        }

        $attribution = null;
        if (isset($parsed['source_attribution']) && \is_string($parsed['source_attribution'])) {
            $trimmed = trim($parsed['source_attribution']);
            $attribution = $trimmed !== '' ? $trimmed : null;
        }

        $links = [];
        if (isset($parsed['source_links_out']) && \is_array($parsed['source_links_out'])) {
            foreach ($parsed['source_links_out'] as $candidate) {
                if (!\is_string($candidate)) {
                    continue;
                }
                $url = trim($candidate);
                if ($url === '' || filter_var($url, \FILTER_VALIDATE_URL) === false) {
                    continue;
                }
                $links[] = $url;
            }
        }

        return new SourceAttributionResult($attribution, $links);
    }

    /**
     * Tolerate common Haiku JSON-wrapping quirks (triple backticks with
     * or without `json` language tag). We ask for raw JSON in the system
     * prompt but defend anyway.
     */
    private function stripMarkdownFences(string $content): string
    {
        if (!str_starts_with($content, '```')) {
            return $content;
        }

        $withoutOpen = (string) preg_replace('/^```[a-zA-Z]*\r?\n/', '', $content);

        return (string) preg_replace('/\r?\n```\s*$/', '', $withoutOpen);
    }
}
