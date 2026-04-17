<?php

declare(strict_types=1);

namespace App\Service\Verification;

use App\Entity\PressRelease;
use App\Entity\Topic;
use App\Repository\AppSettingRepository;
use App\Service\Ai\Provider\GeminiCliService;
use Psr\Log\LoggerInterface;

/**
 * Topic-level semantic deduplication for press releases inside a time window.
 *
 * Replaces the cluster-bound contamination gate (see {@see \App\Service\Clustering\SemanticClusterVerifier}).
 * Per ADR-019 D3: given press releases P1..Pn classified on topic T within a
 * fixed window (default 24h), flag pairs whose semantic similarity exceeds the
 * configured threshold as candidate duplicates. The output is consumed at
 * article generation time to prevent two press releases describing the same
 * event from producing two articles.
 *
 * Infrastructure characteristics preserved from the legacy verifier:
 * - **Fail-open contract**: any LLM/transport/parse failure returns an empty
 *   pairs list. Callers must treat "no duplicates flagged" as the safe default.
 *   This service must NEVER throw to its caller.
 * - **AppSettings-driven thresholds**: existing `cluster_semantic_*` keys are
 *   reused (slated for rename in T52.11; intentionally not renamed here to
 *   keep T52.8 scope contained).
 * - **Gemini-backed**: bulk classification fits the cheap-model routing tier.
 *
 * @see \App\Service\Clustering\SemanticClusterVerifier deprecated cluster-bound counterpart
 */
class SemanticVerifierService
{
    private const GEMINI_TIMEOUT = 120;
    private const MAX_PRS_PER_PROMPT = 20;

    public function __construct(
        private readonly GeminiCliService $geminiCli,
        private readonly AppSettingRepository $appSettings,
        private readonly LoggerInterface $logger,
    ) {}

    public function isEnabled(): bool
    {
        return $this->appSettings->getBool('cluster_semantic_verification_enabled', false);
    }

    /**
     * Identify duplicate press release pairs within a topic + time window.
     *
     * Sends the topic context plus the candidate press release titles to the
     * LLM, which returns pairs of PR ids judged to describe the same event with
     * a similarity score. Pairs below the configured threshold are filtered
     * out. The threshold is read from the existing `cluster_semantic_min_confidence`
     * AppSetting (default 0.70).
     *
     * @param Topic              $topic         The topic context shared by all candidates.
     * @param list<PressRelease> $pressReleases Candidate PRs already classified on $topic and falling inside the window.
     * @param int                $windowHours   The duration of the inspection window (informational; passed into the prompt).
     *
     * @return array<int, array{0: int, 1: int, 2: float}> Tuples of [pr1Id, pr2Id, similarityScore]. Empty list if disabled,
     *                                                     no candidates, fewer than two candidates, LLM error, or no pairs flagged.
     */
    public function findDuplicatePairsInTopicWindow(
        Topic $topic,
        array $pressReleases,
        int $windowHours,
    ): array {
        if (\count($pressReleases) < 2) {
            return [];
        }

        if (!$this->isEnabled()) {
            return [];
        }

        // Defensive cap: max observed cluster size is 5; topic-window queries
        // are bounded by article_generation.* defaults. Hard ceiling avoids
        // pathological prompt growth if upstream changes.
        $candidates = \array_slice($pressReleases, 0, self::MAX_PRS_PER_PROMPT);
        $minConfidence = $this->appSettings->getFloat('cluster_semantic_min_confidence', 0.70);

        try {
            $prompt = $this->buildPrompt($topic, $candidates, $windowHours);

            $rawOutput = $this->geminiCli->execute($prompt, [
                'timeout' => self::GEMINI_TIMEOUT,
            ]);

            return $this->parseResponse($rawOutput, $candidates, $minConfidence);
        } catch (\Throwable $e) {
            $this->logger->warning('SemanticVerifierService: dedup call failed, returning no pairs (fail-open)', [
                'topicId' => $topic->getId(),
                'topicSlug' => $topic->getSlug(),
                'candidateCount' => \count($candidates),
                'windowHours' => $windowHours,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param list<PressRelease> $candidates
     */
    private function buildPrompt(Topic $topic, array $candidates, int $windowHours): string
    {
        $topicTitle = $topic->getTitle() ?? '(fără titlu)';
        $topicSlug = $topic->getSlug() ?? '(fără slug)';

        $lines = [];
        foreach ($candidates as $pr) {
            $lines[] = sprintf('- id=%d: %s', $pr->getId() ?? 0, $pr->getTitle());
        }
        $candidateList = implode("\n", $lines);

        return <<<PROMPT
Ești un editor de știri. Identifică perechile de comunicate de presă care descriu EXACT ACELAȘI eveniment specific (duplicate semantice), nu doar același subiect general.

CONTEXT TOPIC:
- titlu: {$topicTitle}
- slug: {$topicSlug}
- fereastră temporală: ultimele {$windowHours} ore

COMUNICATE DE PRESĂ DE COMPARAT (id + titlu):
{$candidateList}

Răspunde DOAR cu un array JSON valid, fără alt text, listând DOAR perechile considerate duplicate:
[
  {"pr1": <id>, "pr2": <id>, "similarity": 0.0-1.0, "reason": "explicație scurtă max 20 cuvinte"}
]

Dacă NU există perechi duplicate, răspunde cu array gol: []

REGULI STRICTE:
- O pereche e duplicat DOAR dacă ambele comunicate descriu același incident, decizie sau anunț specific
- Două comunicate despre același topic general (ex: alegeri, război) NU sunt automat duplicate
- Două comunicate despre aceeași persoană (ex: Maia Sandu) NU sunt automat duplicate
- Două comunicate din aceeași perioadă NU sunt automat duplicate
- similarity=1.0 dacă sunt identice ca subiect, 0.7-0.9 dacă există suprapunere mare, sub 0.7 nu raporta perechea
- Nu inventa id-uri; folosește doar id-urile din lista de mai sus
- Nu raporta o pereche cu același id de două ori (pr1 ≠ pr2)
PROMPT;
    }

    /**
     * @param list<PressRelease> $candidates
     *
     * @return array<int, array{0: int, 1: int, 2: float}>
     */
    private function parseResponse(string $rawOutput, array $candidates, float $minConfidence): array
    {
        try {
            $items = $this->geminiCli->extractJsonArray($rawOutput);
        } catch (\Throwable $e) {
            $this->logger->warning('SemanticVerifierService: failed to parse JSON, returning no pairs (fail-open)', [
                'error' => $e->getMessage(),
                'rawLength' => mb_strlen($rawOutput),
            ]);

            return [];
        }

        $validIds = [];
        foreach ($candidates as $pr) {
            $id = $pr->getId();
            if ($id !== null) {
                $validIds[$id] = true;
            }
        }

        $pairs = [];
        $seen = [];

        foreach ($items as $item) {
            if (!\is_array($item)) {
                continue;
            }

            $pr1 = isset($item['pr1']) ? (int) $item['pr1'] : 0;
            $pr2 = isset($item['pr2']) ? (int) $item['pr2'] : 0;
            $similarity = isset($item['similarity']) ? (float) $item['similarity'] : 0.0;

            if ($pr1 <= 0 || $pr2 <= 0 || $pr1 === $pr2) {
                continue;
            }

            if (!isset($validIds[$pr1], $validIds[$pr2])) {
                $this->logger->info('SemanticVerifierService: ignoring pair with id outside candidate set', [
                    'pr1' => $pr1,
                    'pr2' => $pr2,
                ]);
                continue;
            }

            if ($similarity < $minConfidence) {
                continue;
            }

            // Normalize order so (a,b) and (b,a) collapse, then dedupe.
            $a = min($pr1, $pr2);
            $b = max($pr1, $pr2);
            $key = $a . ':' . $b;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $pairs[] = [$a, $b, $similarity];
        }

        return $pairs;
    }
}
