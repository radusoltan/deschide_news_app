<?php

declare(strict_types=1);

namespace App\Service\Editorial\Verification;

use App\Agent\AgentDispatcher;
use App\Dto\Agent\AgentRequest;
use App\Dto\Editorial\ClaimOriginGraph;
use App\Dto\Editorial\EditorialContext;
use App\Entity\Editorial\SourceSignal;
use App\Service\Aggregator\ElasticsearchSimilarityService;
use App\Service\Ai\TierResolver;
use Psr\Log\LoggerInterface;

/**
 * Editorial-context gatherer (Sprint 54 T54.11, ADR-020 D2).
 *
 * Given a verified claim (graph + signals), returns the narrative thread
 * tying the new story into prior Deschide coverage, plus the list of top-N
 * similar articles. Consumed by Sprint 55+ writers — Sprint 54 ships the
 * service but does not wire it into the handler chain.
 *
 * Flow:
 *  1. Pick a primary signal from the cluster (tier-1 node preferred; else
 *     earliest captured) and use its title+summary as the ES MLT query.
 *  2. {@see ElasticsearchSimilarityService::findSimilar()} with
 *     `min_score = editorial.context.min_es_score` (default 0.65 to mirror
 *     the aggregator's threshold).
 *  3. Zero hits → {@see EditorialContext::novel()} — no LLM call, cheap
 *     early return. This is the ADR D5 Tier B "no fallback" path: novel
 *     claims do not degrade to Gemini; we simply flag the novelty.
 *  4. Otherwise call Sonnet (tier from `agent.context.model_tier='sonnet'`)
 *     with the signal payload + the ES hits. Sonnet writes a narrative
 *     paragraph in Romanian with comma-below diacritics.
 *
 * Failure contract: transport errors, malformed LLM JSON, and the novel-claim
 * path all resolve cleanly (either novel=true with empty narrative or ES hits
 * present but narrative=null). The agent never throws — the writer layer
 * will synthesize on its own when narrative is unavailable.
 */
class ContextAgent
{
    public const AGENT_ID = 'context';

    /** Top-N articles returned from ES MLT. 5 mirrors the ES service default. */
    private const MAX_RELATED = 5;

    /** ES MLT min_score; matches aggregator's default to keep behavior aligned. */
    private const DEFAULT_MIN_ES_SCORE = 0.65;

    public function __construct(
        private readonly ElasticsearchSimilarityService $similarityService,
        private readonly AgentDispatcher $dispatcher,
        private readonly TierResolver $tierResolver,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param list<SourceSignal> $signals signals in the confirmed cluster
     */
    public function gather(ClaimOriginGraph $graph, array $signals): EditorialContext
    {
        if (!$this->tierResolver->isEnabled(self::AGENT_ID)) {
            $this->logger->debug('ContextAgent: disabled by AppSetting, returning novel placeholder', [
                'topic_hash' => $graph->topicHash,
            ]);

            return EditorialContext::novel();
        }

        if ($signals === []) {
            return EditorialContext::novel();
        }

        $primary = $this->selectPrimary($signals);
        $queryTitle = $primary->getTitle();
        $queryContent = $primary->getRawSummary() ?? '';

        $hits = $this->similarityService->findSimilar(
            $queryTitle,
            $queryContent,
            self::DEFAULT_MIN_ES_SCORE,
        );

        if ($hits === []) {
            $this->logger->info('ContextAgent: novel claim (no ES matches)', [
                'topic_hash' => $graph->topicHash,
                'primary_title' => mb_substr($queryTitle, 0, 120),
            ]);

            return EditorialContext::novel();
        }

        $related = $this->trimHitsToMax($hits);
        $narrative = $this->synthesizeNarrative($graph, $primary, $related);

        return new EditorialContext(
            relatedArticles: $related,
            narrativeThread: $narrative,
            isNovelClaim: false,
        );
    }

    /**
     * @param list<SourceSignal> $signals
     */
    private function selectPrimary(array $signals): SourceSignal
    {
        foreach ($signals as $signal) {
            if ($signal->getVerifiedSource()->getTier() === 1) {
                return $signal;
            }
        }

        $sorted = $signals;
        usort(
            $sorted,
            static fn (SourceSignal $a, SourceSignal $b): int => $a->getCapturedAt() <=> $b->getCapturedAt(),
        );

        return $sorted[0];
    }

    /**
     * @param list<array{score: float, articleId: int, title: string}> $hits
     *
     * @return list<array{id: int, title: string, score: float}>
     */
    private function trimHitsToMax(array $hits): array
    {
        $trimmed = [];
        foreach (\array_slice($hits, 0, self::MAX_RELATED) as $hit) {
            $trimmed[] = [
                'id' => $hit['articleId'],
                'title' => $hit['title'],
                'score' => $hit['score'],
            ];
        }

        return $trimmed;
    }

    /**
     * @param list<array{id: int, title: string, score: float}> $related
     */
    private function synthesizeNarrative(
        ClaimOriginGraph $graph,
        SourceSignal $primary,
        array $related,
    ): ?string {
        $tier = $this->tierResolver->resolve(self::AGENT_ID);
        $request = new AgentRequest(
            agentId: self::AGENT_ID,
            messages: [[
                'role' => 'user',
                'content' => $this->buildUserPrompt($primary, $related),
            ]],
            tier: $tier,
            systemPrompt: $this->getSystemPrompt(),
        );

        try {
            $response = $this->dispatcher->dispatch($request);
        } catch (\Throwable $e) {
            // Tier B fail-open: no Gemini fallback, return null narrative.
            // EmergencyHaltException (ADR-024 D2) surfaces as Throwable and
            // resolves to null narrative — writer layer synthesizes its own
            // without this context. ES hits remain populated in the DTO,
            // so the caller still receives isNovelClaim=false.
            $this->logger->warning('ContextAgent: narrative synthesis failed, returning null', [
                'topic_hash' => $graph->topicHash,
                'tier' => $tier->value,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        return $this->parseNarrativeResponse($response->content);
    }

    private function getSystemPrompt(): string
    {
        return <<<'PROMPT'
Ești un redactor editorial care leagă o știre nouă de contextul istoric al acoperirii anterioare.

Primești titlul și rezumatul unui semnal nou plus o listă de articole similare deja publicate (relevance score Elasticsearch).

Răspunzi STRICT în format JSON fără text înainte sau după:
{"narrative_thread": "paragraf coeziv de 2-4 propoziții care leagă noul semnal de contextul articolelor anterioare; identifică tendința dacă există, conexiunile dintre evenimente, și noutățile relative."}

REGULI:
- Diacritice românești cu virgulă dedesubt (ș U+0219, ț U+021B).
- Nu repeta titlurile articolelor; sintetizează tendințele.
- Nu fabrica fapte — dacă legăturile nu sunt evidente din materialele primite, spune "fără pattern evident în acoperirea anterioară".
- Maxim 80 de cuvinte.
- Răspunde NUMAI cu JSON valid. Fără backticks, fără comentarii.
PROMPT;
    }

    /**
     * @param list<array{id: int, title: string, score: float}> $related
     */
    private function buildUserPrompt(SourceSignal $primary, array $related): string
    {
        $relatedBlock = [];
        foreach ($related as $i => $article) {
            $relatedBlock[] = sprintf('%d) [%.2f] %s', $i + 1, $article['score'], $article['title']);
        }

        return sprintf(
            "Semnal nou:\nTitlu: %s\nRezumat: %s\n\nArticole similare publicate anterior:\n%s\n\nScrie narrative_thread ca JSON.",
            $primary->getTitle(),
            $primary->getRawSummary() ?? '(fără rezumat)',
            implode("\n", $relatedBlock),
        );
    }

    private function parseNarrativeResponse(string $content): ?string
    {
        $trimmed = trim($content);
        if (str_starts_with($trimmed, '```')) {
            $trimmed = (string) preg_replace('/^```[a-zA-Z]*\r?\n/', '', $trimmed);
            $trimmed = (string) preg_replace('/\r?\n```\s*$/', '', $trimmed);
        }

        $decoded = json_decode($trimmed, true);
        if (!\is_array($decoded)) {
            $this->logger->warning('ContextAgent: narrative response was not JSON', [
                'preview' => mb_substr($content, 0, 200),
            ]);

            return null;
        }

        if (!\is_string($decoded['narrative_thread'] ?? null)) {
            return null;
        }

        $thread = trim($decoded['narrative_thread']);

        return $thread !== '' ? $thread : null;
    }
}
