<?php

declare(strict_types=1);

namespace App\Service\Clustering;

use App\Dto\Clustering\VerificationResult;
use App\Repository\AppSettingRepository;
use App\Service\Ai\Provider\GeminiCliService;
use Psr\Log\LoggerInterface;

/**
 * Binary "same story?" classifier using Gemini CLI.
 *
 * Given a cluster headline and candidate PR titles, determines whether each
 * candidate is about the SAME specific event/decision/incident as the cluster.
 *
 * Fail-open design: if Gemini is unavailable, all candidates are accepted.
 *
 * @deprecated since Sprint 52 (T52.8) — superseded by
 *             {@see \App\Service\Verification\SemanticVerifierService}
 *             which operates at the Topic + time-window level instead of
 *             cluster headlines. Will be removed in T52.12 together with the
 *             rest of the StoryCluster infrastructure (see ADR-019).
 */
class SemanticClusterVerifier
{
    private const BATCH_SIZE = 20;
    private const GEMINI_TIMEOUT = 120;

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
     * Verify if a single PR title is about the same story as the cluster headline.
     *
     * @deprecated since Sprint 52 (T52.8) — use
     *             {@see \App\Service\Verification\SemanticVerifierService::findDuplicatePairsInTopicWindow()}.
     *             Removal scheduled for T52.12.
     */
    public function verify(string $prTitle, string $clusterHeadline): VerificationResult
    {
        $results = $this->verifyBatch([['title' => $prTitle]], $clusterHeadline);

        return $results[0] ?? VerificationResult::failOpen('Empty batch result');
    }

    /**
     * Batch verify: up to BATCH_SIZE PR titles against a cluster headline.
     *
     * @param list<array{title: string, id?: int}> $candidates
     * @return list<VerificationResult>
     *
     * @deprecated since Sprint 52 (T52.8) — use
     *             {@see \App\Service\Verification\SemanticVerifierService::findDuplicatePairsInTopicWindow()}.
     *             Removal scheduled for T52.12.
     */
    public function verifyBatch(array $candidates, string $clusterHeadline): array
    {
        if ($candidates === []) {
            return [];
        }

        if (!$this->isEnabled()) {
            return array_map(
                fn () => VerificationResult::pass('Verification disabled'),
                $candidates,
            );
        }

        $chunks = array_chunk($candidates, self::BATCH_SIZE);
        $results = [];

        foreach ($chunks as $chunk) {
            try {
                $batchResults = $this->callGemini($chunk, $clusterHeadline);
                array_push($results, ...$batchResults);
            } catch (\Throwable $e) {
                $this->logger->warning('SemanticClusterVerifier: Gemini failed, accepting all candidates', [
                    'error' => $e->getMessage(),
                    'candidateCount' => \count($chunk),
                ]);

                foreach ($chunk as $ignored) {
                    $results[] = VerificationResult::failOpen('Gemini error: ' . $e->getMessage());
                }
            }
        }

        return $results;
    }

    /**
     * @param list<array{title: string, id?: int}> $candidates
     * @return list<VerificationResult>
     */
    private function callGemini(array $candidates, string $clusterHeadline): array
    {
        $prompt = $this->buildPrompt($candidates, $clusterHeadline);

        $rawOutput = $this->geminiCli->execute($prompt, [
            'timeout' => self::GEMINI_TIMEOUT,
        ]);

        return $this->parseResponse($rawOutput, \count($candidates));
    }

    /**
     * @param list<array{title: string, id?: int}> $candidates
     */
    private function buildPrompt(array $candidates, string $clusterHeadline): string
    {
        $lines = [];
        foreach ($candidates as $i => $c) {
            $lines[] = ($i + 1) . '. ' . $c['title'];
        }
        $candidateList = implode("\n", $lines);

        return <<<PROMPT
Ești un editor de știri. Analizează dacă fiecare titlu de știre din lista de mai jos este despre ACELAȘI eveniment sau subiect ca TITLUL DE REFERINȚĂ.

TITLU DE REFERINȚĂ: {$clusterHeadline}

TITLURI DE VERIFICAT:
{$candidateList}

Răspunde DOAR cu un array JSON valid, fără alt text:
[
  {"index": 1, "same_story": true, "confidence": 0.95, "reason": "explicație scurtă max 20 cuvinte"}
]

REGULI STRICTE:
- same_story=true DOAR dacă ambele sunt despre EXACT același eveniment, decizie, sau incident specific
- Două știri din aceeași PERIOADĂ (ex: Paște) NU sunt automat despre același subiect
- Două știri din aceeași ȚARĂ (ex: Moldova, Ucraina) NU sunt automat despre același subiect
- Două știri despre aceeași PERSOANĂ (ex: Maia Sandu) NU sunt automat despre același subiect
- Meteo, lifestyle, sport, entertainment NU sunt niciodată despre același subiect ca politică/conflict
- confidence=1.0 dacă ești sigur, 0.5 dacă ești incert, 0.0 dacă sunt clar diferite
PROMPT;
    }

    /**
     * @return list<VerificationResult>
     */
    private function parseResponse(string $rawOutput, int $expectedCount): array
    {
        try {
            $items = $this->geminiCli->extractJsonArray($rawOutput);
        } catch (\Throwable $e) {
            $this->logger->warning('SemanticClusterVerifier: failed to parse JSON, accepting all', [
                'error' => $e->getMessage(),
                'rawLength' => mb_strlen($rawOutput),
            ]);

            return array_fill(0, $expectedCount, VerificationResult::failOpen('JSON parse error'));
        }

        $results = [];
        for ($i = 0; $i < $expectedCount; $i++) {
            $item = $this->findItemByIndex($items, $i + 1);
            if ($item === null) {
                $results[] = VerificationResult::failOpen('Missing index ' . ($i + 1) . ' in response');
                continue;
            }

            $sameStory = (bool) ($item['same_story'] ?? true);
            $confidence = (float) ($item['confidence'] ?? 0.5);
            $reason = (string) ($item['reason'] ?? '');

            $results[] = new VerificationResult($sameStory, $confidence, $reason);
        }

        return $results;
    }

    /**
     * @param list<mixed> $items
     * @return array<string, mixed>|null
     */
    private function findItemByIndex(array $items, int $index): ?array
    {
        foreach ($items as $item) {
            if (\is_array($item) && ($item['index'] ?? null) === $index) {
                return $item;
            }
        }

        return null;
    }
}
