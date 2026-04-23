<?php

declare(strict_types=1);

namespace App\Service\Editorial\Verification;

use App\Agent\AgentDispatcher;
use App\Dto\Agent\AgentRequest;
use App\Dto\Editorial\ClaimOriginGraph;
use App\Entity\Editorial\SourceSignal;
use App\Repository\AppSettingRepository;
use App\Repository\Editorial\SourceSignalRepository;
use App\Service\Aggregator\ElasticsearchSimilarityService;
use App\Service\Ai\TierResolver;
use Psr\Log\LoggerInterface;

/**
 * Takes a stabilized set of signal ids (from {@see SignalStabilizationBuffer})
 * and turns them into zero or more confirmed clusters, each with a
 * {@see ClaimOriginGraph} ready for {@see VerificationGate} (T54.9).
 *
 * Algorithm (Sprint 54 T54.8, ADR-020 D3):
 *   1. Hydrate signals from the repository.
 *   2. Group by ES similarity overlap: each signal is compared against
 *      {@see ElasticsearchSimilarityService::findSimilar()}; signals whose
 *      matched-article sets overlap by at least `editorial.aggregator.min_cluster_overlap`
 *      distinct articleIds are considered candidates for the same story.
 *   3. Run the Haiku semantic gate per candidate cluster: "are these N
 *      signals about the same claim?" → JSON {is_same_claim, confidence}.
 *      Reject clusters where confidence < threshold; fail-open on LLM error
 *      (keep the ES grouping, log warning).
 *   4. For each confirmed cluster, build the claim-origin graph.
 *
 * Return type is list of ClaimOriginGraph — one per cluster. Callers
 * (handler in this sprint) dispatch one {@see \App\Message\Editorial\VerifyClaimMessage}
 * per entry.
 */
class SignalAggregator
{
    public const AGENT_ID = 'signal_aggregator';

    public function __construct(
        private readonly SourceSignalRepository $sourceSignalRepository,
        private readonly ElasticsearchSimilarityService $similarityService,
        private readonly ClaimOriginGraphBuilder $graphBuilder,
        private readonly AgentDispatcher $dispatcher,
        private readonly TierResolver $tierResolver,
        private readonly AppSettingRepository $appSettings,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param list<int> $signalIds
     *
     * @return list<ClaimOriginGraph>
     */
    public function aggregate(string $topicHash, array $signalIds): array
    {
        if ($signalIds === []) {
            return [];
        }

        /** @var list<SourceSignal> $signals */
        $signals = [];
        foreach ($signalIds as $id) {
            $signal = $this->sourceSignalRepository->find($id);
            if ($signal !== null) {
                $signals[] = $signal;
            }
        }

        if (\count($signals) < 1) {
            $this->logger->warning('SignalAggregator: no signals hydrated from ids', [
                'topic_hash' => $topicHash,
                'requested' => \count($signalIds),
            ]);

            return [];
        }

        $candidateClusters = $this->groupBySimilarityOverlap($signals);

        $confirmedGraphs = [];
        foreach ($candidateClusters as $cluster) {
            // A single-signal "cluster" is still a valid claim (ADR-020 D3
            // Rule 1 covers single-chain + tier-1); skip the LLM gate for it.
            if (\count($cluster) === 1) {
                $confirmedGraphs[] = $this->graphBuilder->build($topicHash, $cluster);

                continue;
            }

            if ($this->passesSemanticGate($cluster)) {
                $confirmedGraphs[] = $this->graphBuilder->build($topicHash, $cluster);
            }
        }

        $this->logger->info('SignalAggregator: produced graphs', [
            'topic_hash' => $topicHash,
            'signals_in' => \count($signals),
            'candidate_clusters' => \count($candidateClusters),
            'confirmed_graphs' => \count($confirmedGraphs),
        ]);

        return $confirmedGraphs;
    }

    /**
     * Bucket signals by the intersection of their ES similar-article sets.
     * A signal joins an existing bucket if it shares at least
     * `editorial.aggregator.min_cluster_overlap` article ids with any
     * signal already in that bucket.
     *
     * @param list<SourceSignal> $signals
     *
     * @return list<list<SourceSignal>>
     */
    private function groupBySimilarityOverlap(array $signals): array
    {
        $minScore = (float) $this->appSettings->get('editorial.aggregator.min_es_score', '0.65');
        $minOverlap = max(1, $this->appSettings->getInt('editorial.aggregator.min_cluster_overlap', 2));

        /** @var list<array{signals: list<SourceSignal>, matchedIds: array<int, true>}> $buckets */
        $buckets = [];

        foreach ($signals as $signal) {
            $matched = $this->similarityService->findSimilar(
                $signal->getTitle(),
                $signal->getRawSummary() ?? '',
                $minScore,
            );

            /** @var array<int, true> $matchedIds */
            $matchedIds = [];
            foreach ($matched as $hit) {
                $matchedIds[(int) $hit['articleId']] = true;
            }

            $placed = false;
            foreach ($buckets as $idx => $bucket) {
                if ($this->intersectionSize($bucket['matchedIds'], $matchedIds) >= $minOverlap) {
                    $buckets[$idx]['signals'][] = $signal;
                    $buckets[$idx]['matchedIds'] += $matchedIds;
                    $placed = true;
                    break;
                }
            }

            if (!$placed) {
                $buckets[] = ['signals' => [$signal], 'matchedIds' => $matchedIds];
            }
        }

        return array_map(static fn (array $b): array => $b['signals'], $buckets);
    }

    /**
     * @param array<int, true> $a
     * @param array<int, true> $b
     */
    private function intersectionSize(array $a, array $b): int
    {
        $count = 0;
        foreach ($a as $k => $_) {
            if (isset($b[$k])) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @param list<SourceSignal> $cluster
     */
    private function passesSemanticGate(array $cluster): bool
    {
        if (!$this->tierResolver->isEnabled(self::AGENT_ID)) {
            $this->logger->debug('SignalAggregator: semantic gate disabled, accepting cluster by ES overlap', [
                'cluster_size' => \count($cluster),
            ]);

            return true;
        }

        $threshold = (float) $this->appSettings->get('editorial.aggregator.llm_gate_confidence_threshold', '0.7');

        $tier = $this->tierResolver->resolve(self::AGENT_ID);
        $request = new AgentRequest(
            agentId: self::AGENT_ID,
            messages: [[
                'role' => 'user',
                'content' => $this->buildSemanticGatePrompt($cluster),
            ]],
            tier: $tier,
            systemPrompt: $this->getSemanticGateSystemPrompt(),
        );

        try {
            $response = $this->dispatcher->dispatch($request);
        } catch (\Throwable $e) {
            // Fail-open: keep ES grouping if the gate fails.
            // EmergencyHaltException (ADR-024 D2) surfaces here as a Throwable
            // and fails-open identically to transport errors — the cluster
            // retains its ES grouping, which is the safe default behavior
            // during halt.
            $this->logger->warning('SignalAggregator: semantic gate LLM failed, fail-open', [
                'cluster_size' => \count($cluster),
                'error' => $e->getMessage(),
            ]);

            return true;
        }

        $decision = $this->parseSemanticGateResponse($response->content);

        $this->logger->info('SignalAggregator: semantic gate decision', [
            'cluster_size' => \count($cluster),
            'is_same_claim' => $decision['is_same_claim'] ?? null,
            'confidence' => $decision['confidence'] ?? null,
            'threshold' => $threshold,
        ]);

        $same = (bool) ($decision['is_same_claim'] ?? false);
        $confidence = (float) ($decision['confidence'] ?? 0.0);

        return $same && $confidence >= $threshold;
    }

    private function getSemanticGateSystemPrompt(): string
    {
        return <<<'PROMPT'
Ești un analist editorial. Decizi dacă un grup de titluri și rezumate descriu aceeași știre (același eveniment, aceiași actori, același moment în timp) sau știri distincte care doar se aseamănă tematic.

Răspunzi STRICT în format JSON fără text înainte sau după:
{"is_same_claim": true|false, "confidence": 0.0-1.0, "reasoning": "scurtă justificare"}

REGULI:
- "is_same_claim" = true DOAR când semnalele descriu exact aceeași știre (același eveniment, nu doar aceeași temă generală).
- "confidence" este scor între 0.0 și 1.0 care reflectă cât de sigur ești de decizie.
- "reasoning" este o propoziție scurtă în română cu diacritice corecte cu virgulă dedesubt (ș U+0219, ț U+021B).
- Răspunde NUMAI cu JSON valid. Fără backticks, fără comentarii.
PROMPT;
    }

    /**
     * @param list<SourceSignal> $cluster
     */
    private function buildSemanticGatePrompt(array $cluster): string
    {
        $items = [];
        foreach ($cluster as $i => $signal) {
            $items[] = sprintf(
                "%d) Sursă: %s\n   Titlu: %s\n   Rezumat: %s",
                $i + 1,
                $signal->getVerifiedSource()->getSlug(),
                $signal->getTitle(),
                $signal->getRawSummary() ?? '(fără rezumat)',
            );
        }

        return "Semnale de evaluat:\n\n"
            . implode("\n\n", $items)
            . "\n\nSpun toate despre aceeași știre? Răspunde cu JSON.";
    }

    /**
     * @return array{is_same_claim?: bool, confidence?: float, reasoning?: string}
     */
    private function parseSemanticGateResponse(string $content): array
    {
        $trimmed = trim($content);
        if (str_starts_with($trimmed, '```')) {
            $trimmed = (string) preg_replace('/^```[a-zA-Z]*\r?\n/', '', $trimmed);
            $trimmed = (string) preg_replace('/\r?\n```\s*$/', '', $trimmed);
        }

        $decoded = json_decode($trimmed, true);
        if (!\is_array($decoded)) {
            $this->logger->warning('SignalAggregator: semantic gate returned non-JSON, treating as rejection', [
                'preview' => mb_substr($content, 0, 200),
            ]);

            return ['is_same_claim' => false, 'confidence' => 0.0];
        }

        /** @var array{is_same_claim?: bool, confidence?: float, reasoning?: string} $decoded */
        return $decoded;
    }
}
