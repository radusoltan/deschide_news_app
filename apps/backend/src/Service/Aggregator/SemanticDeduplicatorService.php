<?php

declare(strict_types=1);

namespace App\Service\Aggregator;

use App\Enum\DeduplicationResult;
use App\Service\ContentDeduplicator;
use App\Service\ContentHasher;
use App\Service\Ai\Provider\GeminiCliException;
use App\Service\Ai\Provider\GeminiCliService;
use Psr\Log\LoggerInterface;

class SemanticDeduplicatorService
{
    private const GEMINI_TIMEOUT = 30;
    private const ES_DUPLICATE_THRESHOLD = 0.82;
    private const ES_REVIEW_THRESHOLD = 0.55;
    private const GEMINI_CONFIDENCE_THRESHOLD = 0.7;

    public function __construct(
        private readonly ContentHasher $contentHasher,
        private readonly ContentDeduplicator $contentDeduplicator,
        private readonly ElasticsearchSimilarityService $esSimilarity,
        private readonly GeminiCliService $geminiCli,
        private readonly LoggerInterface $logger,
    ) {}

    public function evaluate(string $title, string $content): DeduplicationResult
    {
        // L1: Fast SHA-256 hash check
        $hash = $this->contentHasher->hash($content);
        $dupCheck = $this->contentDeduplicator->isDuplicate($hash);

        if ($dupCheck->isDuplicate) {
            $this->logger->info('SemanticDeduplicator: L1 DUPLICATE (hash match)', [
                'title' => mb_substr($title, 0, 80),
                'existingEntity' => $dupCheck->existingEntityType,
                'existingId' => $dupCheck->existingEntityId,
            ]);

            return DeduplicationResult::DUPLICATE;
        }

        // L2: Elasticsearch more_like_this similarity
        if (!$this->esSimilarity->isEnabled()) {
            $this->logger->debug('SemanticDeduplicator: ES disabled, treating as UNIQUE');

            return DeduplicationResult::UNIQUE;
        }

        $similar = $this->esSimilarity->findSimilar($title, $content, self::ES_REVIEW_THRESHOLD);

        if ($similar === []) {
            $this->logger->info('SemanticDeduplicator: L2 UNIQUE (no ES matches)', [
                'title' => mb_substr($title, 0, 80),
            ]);

            return DeduplicationResult::UNIQUE;
        }

        $topScore = $similar[0]['score'];

        if ($topScore > self::ES_DUPLICATE_THRESHOLD) {
            $this->logger->info('SemanticDeduplicator: L2 DUPLICATE (ES score > {threshold})', [
                'title' => mb_substr($title, 0, 80),
                'score' => $topScore,
                'matchTitle' => $similar[0]['title'],
                'threshold' => self::ES_DUPLICATE_THRESHOLD,
            ]);

            return DeduplicationResult::DUPLICATE;
        }

        // L3: Gray zone (0.6-0.8) — use Gemini CLI for semantic comparison
        $this->logger->info('SemanticDeduplicator: L3 gray zone, consulting Gemini CLI', [
            'title' => mb_substr($title, 0, 80),
            'score' => $topScore,
            'matchTitle' => $similar[0]['title'],
        ]);

        return $this->evaluateWithGemini($title, $content, $similar[0]);
    }

    /**
     * @param array{score: float, articleId: int, title: string} $candidate
     */
    private function evaluateWithGemini(string $title, string $content, array $candidate): DeduplicationResult
    {
        $newSnippet = mb_substr(strip_tags($content), 0, 800);
        $prompt = sprintf(
            "Compare these two articles. Answer ONLY with JSON: {\"isDuplicate\": true/false, \"confidence\": 0.0-1.0, \"reason\": \"...\"}\n\n"
            . "Article 1 (new):\nTitle: %s\nContent: %s\n\n"
            . "Article 2 (existing, ES score %.2f):\nTitle: %s",
            $title,
            $newSnippet,
            $candidate['score'],
            $candidate['title'],
        );

        try {
            $output = $this->geminiCli->execute($prompt, ['timeout' => self::GEMINI_TIMEOUT]);
            $json = $this->extractJson($output);

            if ($json === null) {
                $this->logger->warning('SemanticDeduplicator: Gemini returned invalid JSON', [
                    'output' => mb_substr($output, 0, 300),
                ]);

                return DeduplicationResult::NEEDS_REVIEW;
            }

            $isDuplicate = $json['isDuplicate'] ?? false;
            $confidence = (float) ($json['confidence'] ?? 0);
            $reason = $json['reason'] ?? '';

            $this->logger->info('SemanticDeduplicator: L3 Gemini verdict', [
                'isDuplicate' => $isDuplicate,
                'confidence' => $confidence,
                'reason' => $reason,
            ]);

            if ($isDuplicate && $confidence > self::GEMINI_CONFIDENCE_THRESHOLD) {
                return DeduplicationResult::DUPLICATE;
            }

            return DeduplicationResult::UNIQUE;
        } catch (GeminiCliException|\Throwable $e) {
            $this->logger->error('SemanticDeduplicator: Gemini CLI exception', [
                'error' => $e->getMessage(),
            ]);

            return DeduplicationResult::NEEDS_REVIEW;
        }
    }

    private function extractJson(string $output): ?array
    {
        // Try to find JSON object in the output
        if (preg_match('/\{[^{}]*"isDuplicate"[^{}]*\}/', $output, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (\is_array($decoded)) {
                return $decoded;
            }
        }

        // Fallback: try to decode the entire output
        $decoded = json_decode($output, true);

        return \is_array($decoded) ? $decoded : null;
    }
}
