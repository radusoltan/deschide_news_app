<?php

declare(strict_types=1);

namespace App\Service\Topic;

use App\Agent\AgentDispatcher;
use App\Agent\Exception\EmergencyHaltException;
use App\Dto\Agent\AgentRequest;
use App\Dto\Topic\TopicDetectionResult;
use App\Entity\PressRelease;
use App\Entity\PressReleaseTopic;
use App\Enum\TopicDetectionMethod;
use App\Repository\TopicRepository;
use App\Service\Ai\Provider\GeminiCliService;
use App\Service\Ai\TierResolver;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * 2-layer topic detector for press releases.
 *
 * Layer 1: Keyword matching against Topic.keywords (deterministic, cheap)
 * Layer 2: LLM fallback when keyword confidence < threshold (async-capable)
 *
 * T57.P6 — migrated to {@see AgentDispatcher} on `topic_classifier` agent id
 * (Haiku tier per ADR-024 D1). Gemini CLI is retained as an inline fallback
 * on dispatcher throw (retry exhausted / provider transport failure) until
 * T57.P8 retires the downgrade-only policy. {@see EmergencyHaltException}
 * is explicitly rethrown past the fallback so `editorial.emergency_halt`
 * halts the LLM layer cleanly — the outer catch-all then converts it to
 * the fail-open empty-array result (keyword-only behaviour).
 */
final class PressReleaseTopicDetector
{
    public const AGENT_ID = 'topic_classifier';

    private const float GEMINI_TRIGGER_THRESHOLD = 0.7;
    private const int GEMINI_TIMEOUT = 60;
    private const int MAX_TOPICS_PER_PR = 5;

    public function __construct(
        private readonly TopicRepository $topicRepository,
        private readonly EntityManagerInterface $em,
        private readonly AgentDispatcher $dispatcher,
        private readonly TierResolver $tierResolver,
        private readonly GeminiCliService $geminiCli,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Detect topics for a press release and persist PressReleaseTopic pivots.
     *
     * @return list<PressReleaseTopic> Persisted pivot entities
     */
    public function detectAndPersist(PressRelease $pressRelease, bool $useGeminiFallback = true): array
    {
        $results = $this->detect($pressRelease, $useGeminiFallback);

        if ($results === []) {
            return [];
        }

        $topicMap = [];
        foreach ($this->topicRepository->findBy(['id' => array_column($results, 'topicId')]) as $topic) {
            $topicMap[$topic->getId()] = $topic;
        }

        $pivots = [];
        foreach ($results as $result) {
            $topic = $topicMap[$result->topicId] ?? null;
            if ($topic === null) {
                continue;
            }

            $pivot = new PressReleaseTopic(
                $pressRelease,
                $topic,
                $result->confidence,
                $result->detectedBy,
            );
            $this->em->persist($pivot);
            $pressRelease->addPressReleaseTopic($pivot);
            $pivots[] = $pivot;
        }

        $this->em->flush();

        return $pivots;
    }

    /**
     * Detect topics without persisting (for dry-run / preview).
     *
     * @return list<TopicDetectionResult>
     */
    public function detect(PressRelease $pressRelease, bool $useGeminiFallback = true): array
    {
        $keywordResults = $this->matchKeywords($pressRelease);

        $topConfidence = $keywordResults !== [] ? max(array_column($keywordResults, 'confidence')) : 0.0;

        if ($useGeminiFallback && $topConfidence < self::GEMINI_TRIGGER_THRESHOLD) {
            $llmResults = $this->detectWithLlm($pressRelease);
            if ($llmResults !== []) {
                return $this->mergeResults($keywordResults, $llmResults);
            }
        }

        return $keywordResults;
    }

    /**
     * Layer 1: Keyword matching — tokenize PR text, match against Topic.keywords.
     *
     * @return list<TopicDetectionResult>
     */
    public function matchKeywords(PressRelease $pressRelease): array
    {
        $text = $this->extractSearchableText($pressRelease);
        $tokens = $this->tokenize($text);

        if ($tokens === []) {
            return [];
        }

        $topics = $this->topicRepository->findBy(['isActive' => true]);
        $results = [];

        foreach ($topics as $topic) {
            $keywords = $topic->getKeywords();
            if ($keywords === null || $keywords === []) {
                continue;
            }

            $confidence = $this->calculateKeywordConfidence($tokens, $keywords, $text);
            if ($confidence > 0.0) {
                $results[] = new TopicDetectionResult(
                    $topic->getId(),
                    $confidence,
                    TopicDetectionMethod::KEYWORD,
                );
            }
        }

        // Sort by confidence descending, limit to top N
        usort($results, static fn (TopicDetectionResult $a, TopicDetectionResult $b) => $b->confidence <=> $a->confidence);

        return \array_slice($results, 0, self::MAX_TOPICS_PER_PR);
    }

    /**
     * Layer 2: LLM fallback for uncertain keyword matches.
     *
     * Dispatcher-first (Anthropic via {@see AgentDispatcher}, tier resolved from
     * `agent.topic_classifier.model_tier`), with inline Gemini CLI fallback on
     * retry exhaustion / transport failure. {@see EmergencyHaltException} is
     * rethrown and caught by the outer `\Throwable` clause so the overall
     * failure contract (empty array → keyword-only result) is preserved.
     *
     * @return list<TopicDetectionResult>
     */
    private function detectWithLlm(PressRelease $pressRelease): array
    {
        try {
            $tree = $this->topicRepository->getFullTree('ro');
            $topicList = $this->flattenTreeForPrompt($tree);
            $prompt = $this->buildLlmPrompt($pressRelease, $topicList);

            try {
                $tier = $this->tierResolver->resolve(self::AGENT_ID);
                $response = $this->dispatcher->dispatch(new AgentRequest(
                    agentId: self::AGENT_ID,
                    messages: [['role' => 'user', 'content' => $prompt]],
                    tier: $tier,
                ));
                $output = $response->content;
            } catch (EmergencyHaltException) {
                throw new \RuntimeException('emergency_halt');
            } catch (\Throwable $e) {
                $this->logger->warning('PressReleaseTopicDetector: dispatcher failed, falling back to Gemini', [
                    'id' => $pressRelease->getId(),
                    'error' => $e->getMessage(),
                ]);
                $output = $this->geminiCli->execute($prompt, ['timeout' => self::GEMINI_TIMEOUT]);
            }

            return $this->parseLlmResponse($output);
        } catch (\Throwable $e) {
            $this->logger->warning('Topic detection failed for PR #{id}: {error}', [
                'id' => $pressRelease->getId(),
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    private function extractSearchableText(PressRelease $pressRelease): string
    {
        $parts = [
            $pressRelease->getTitle(),
            $pressRelease->getLead() ?? '',
            strip_tags($pressRelease->getContent()),
        ];

        return implode(' ', $parts);
    }

    /**
     * Tokenize text: lowercase, normalize diacritics for matching, split on word boundaries.
     *
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        $text = mb_strtolower($text);
        // Normalize common diacritics variants for matching
        $text = str_replace(
            ["\u{015F}", "\u{0163}"], // cedilla forms
            ["\u{0219}", "\u{021B}"], // comma-below forms
            $text,
        );

        preg_match_all('/\p{L}[\p{L}\p{Mn}\'-]*/u', $text, $matches);

        return $matches[0];
    }

    /**
     * Calculate confidence from keyword matches using frequency + position weighting.
     *
     * @param list<string> $tokens
     * @param list<string> $keywords
     */
    private function calculateKeywordConfidence(array $tokens, array $keywords, string $fullText): float
    {
        $fullTextLower = mb_strtolower($fullText);
        $fullTextLower = str_replace(
            ["\u{015F}", "\u{0163}"],
            ["\u{0219}", "\u{021B}"],
            $fullTextLower,
        );

        $matchedKeywords = 0;
        $totalWeight = 0.0;

        foreach ($keywords as $keyword) {
            $keywordLower = mb_strtolower((string) $keyword);
            $keywordLower = str_replace(
                ["\u{015F}", "\u{0163}"],
                ["\u{0219}", "\u{021B}"],
                $keywordLower,
            );

            // Multi-word keyword: check full text for substring match
            if (str_contains($keywordLower, ' ')) {
                if (str_contains($fullTextLower, $keywordLower)) {
                    $matchedKeywords++;
                    // Position bonus: matches in first 200 chars (title/lead) score higher
                    $pos = mb_strpos($fullTextLower, $keywordLower);
                    $totalWeight += $pos !== false && $pos < 200 ? 1.5 : 1.0;
                }
                continue;
            }

            // Single-word keyword: check token list
            if (\in_array($keywordLower, $tokens, true)) {
                $matchedKeywords++;
                // Count occurrences for frequency weighting
                $count = \count(array_keys($tokens, $keywordLower, true));
                $totalWeight += min($count * 0.5, 2.0);

                // Position bonus
                $firstPos = array_search($keywordLower, $tokens, true);
                if ($firstPos !== false && $firstPos < 30) { // roughly in title/lead
                    $totalWeight += 0.5;
                }
            }
        }

        if ($matchedKeywords === 0) {
            return 0.0;
        }

        // Base: ratio of matched keywords
        $keywordRatio = $matchedKeywords / \count($keywords);

        // Weighted score: combines ratio with position/frequency bonuses
        $weightedScore = ($keywordRatio * 0.6) + (min($totalWeight / \count($keywords), 1.0) * 0.4);

        return round(min($weightedScore, 1.0), 3);
    }

    /**
     * Flatten topic tree into a compact string for the Gemini prompt.
     *
     * @param list<array<string, mixed>> $tree
     */
    private function flattenTreeForPrompt(array $tree): string
    {
        $lines = [];
        foreach ($tree as $node) {
            $this->flattenNode($node, $lines, 0);
        }

        return implode("\n", $lines);
    }

    /**
     * @param array<string, mixed> $node
     * @param list<string> $lines
     */
    private function flattenNode(array $node, array &$lines, int $depth): void
    {
        $indent = str_repeat('  ', $depth);
        $lines[] = "{$indent}- [{$node['id']}] {$node['title']}";

        foreach ($node['children'] ?? [] as $child) {
            $this->flattenNode($child, $lines, $depth + 1);
        }
    }

    private function buildLlmPrompt(PressRelease $pressRelease, string $topicList): string
    {
        $title = $pressRelease->getTitle();
        $content = mb_substr(strip_tags($pressRelease->getContent()), 0, 2000);

        return <<<PROMPT
Analyze this press release and classify it into 1-5 topics from the taxonomy below.

PRESS RELEASE:
Title: {$title}
Content: {$content}

TOPIC TAXONOMY:
{$topicList}

Return ONLY a JSON array of objects with:
- "topic_id": integer (from the taxonomy IDs above)
- "confidence": float 0.0-1.0

Example: [{"topic_id": 42, "confidence": 0.9}, {"topic_id": 15, "confidence": 0.7}]

Rules:
- Only use topic IDs from the taxonomy above
- Maximum 5 topics
- Minimum confidence 0.3
- Return [] if no topic matches
PROMPT;
    }

    /**
     * Parse the LLM response (dispatcher or Gemini fallback) into typed results.
     *
     * Inline `json_decode` — replaces the {@see GeminiCliService::extractJsonArray}
     * helper coupled to the pre-T57.P6 Gemini-only path. Tolerates markdown code
     * fences that some models still emit (defence-in-depth; Anthropic
     * instructed to omit them but the parser should not be brittle).
     *
     * @return list<TopicDetectionResult>
     */
    private function parseLlmResponse(string $output): array
    {
        $json = $output;
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $json, $m)) {
            $json = $m[1];
        }
        if (preg_match('/\[[\s\S]*\]/', $json, $m)) {
            $json = $m[0];
        }

        $data = json_decode(trim($json), true);
        if (!\is_array($data)) {
            $this->logger->warning('Failed to parse topic detection LLM response');

            return [];
        }

        $results = [];
        foreach ($data as $item) {
            if (!isset($item['topic_id'], $item['confidence'])) {
                continue;
            }

            $topicId = (int) $item['topic_id'];
            $confidence = (float) $item['confidence'];

            if ($topicId <= 0 || $confidence < 0.3) {
                continue;
            }

            $results[] = new TopicDetectionResult(
                $topicId,
                round(min($confidence, 1.0), 3),
                TopicDetectionMethod::LLM,
            );
        }

        return \array_slice($results, 0, self::MAX_TOPICS_PER_PR);
    }

    /**
     * Merge keyword and Gemini results, preferring higher confidence per topic.
     *
     * @param list<TopicDetectionResult> $keywordResults
     * @param list<TopicDetectionResult> $geminiResults
     * @return list<TopicDetectionResult>
     */
    private function mergeResults(array $keywordResults, array $geminiResults): array
    {
        $merged = [];

        foreach ($keywordResults as $r) {
            $merged[$r->topicId] = $r;
        }

        foreach ($geminiResults as $r) {
            if (!isset($merged[$r->topicId]) || $r->confidence > $merged[$r->topicId]->confidence) {
                $merged[$r->topicId] = $r;
            }
        }

        $results = array_values($merged);
        usort($results, static fn (TopicDetectionResult $a, TopicDetectionResult $b) => $b->confidence <=> $a->confidence);

        return \array_slice($results, 0, self::MAX_TOPICS_PER_PR);
    }
}
