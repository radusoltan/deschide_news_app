<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use App\Entity\Editorial\SourceSignal;
use App\Service\TopicDetectorService;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Resolves a cluster's `topic_hash` (an opaque identifier attached to
 * messenger payloads + claim snapshots) to a real {@see \App\Entity\Topic} id
 * so downstream handlers can operate on typed associations.
 *
 * Pre-T56.04, `AggregateSignalsMessageHandler` dispatched `VerifyClaimMessage`
 * without a `topicId`, which in turn left `VerifyClaimMessageHandler` unable
 * to reach {@see \App\Repository\ArticleRepository::findDevelopingStoryForTopic()} —
 * turning the "continue an in-progress developing story" path into dead
 * code. This service closes that gap per ADR-022 D4.
 *
 * Behaviour:
 *  - Redis-backed PSR-6 cache keyed `topic_hash_map.{hash}`.
 *  - Cache hit → return the cached int|null without invoking the LLM.
 *  - Cache miss → delegate to {@see TopicDetectorService} (Gemini-backed),
 *    select the highest-confidence topic (tie-break by input order), cache
 *    the result with 7-day TTL for positive matches and 1-hour TTL for
 *    negatives (so unresolvable clusters don't keep paying the Gemini cost
 *    on every re-flush).
 *  - Cache unavailable or throws → log a warning, return null. Pipeline
 *    continues in the same degraded-but-functional mode it had before
 *    T56.04 (flash-only, no developing-story continuation).
 */
readonly class TopicHashResolver
{
    private const int TTL_POSITIVE_SECONDS = 604800; // 7 days
    private const int TTL_NEGATIVE_SECONDS = 3600;   // 1 hour
    private const string KEY_PREFIX = 'topic_hash_map.';

    /**
     * Confidence strings emitted by TopicDetectorService ranked numerically
     * so we can sort by strongest match. Unknown values fall back to 0.
     *
     * @var array<string, int>
     */
    private const CONFIDENCE_WEIGHTS = [
        'high' => 3,
        'medium' => 2,
        'low' => 1,
    ];

    public function __construct(
        private TopicDetectorService $topicDetector,
        private CacheInterface $cache,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return int|null Topic id, or null when the LLM cannot confidently map
     *                  the representative signal to any seeded topic.
     */
    public function resolve(string $topicHash, SourceSignal $representativeSignal): ?int
    {
        $cacheKey = self::KEY_PREFIX . $topicHash;

        try {
            /** @var int|null $topicId */
            $topicId = $this->cache->get(
                $cacheKey,
                function (ItemInterface $item) use ($topicHash, $representativeSignal): ?int {
                    $resolved = $this->detectViaLlm($representativeSignal);

                    $ttl = $resolved !== null ? self::TTL_POSITIVE_SECONDS : self::TTL_NEGATIVE_SECONDS;
                    $item->expiresAfter($ttl);

                    $this->logger->info('topic_hash_resolver.miss', [
                        'topic_hash' => $topicHash,
                        'topic_id' => $resolved,
                        'signal_id' => $representativeSignal->getId(),
                        'ttl_seconds' => $ttl,
                    ]);

                    return $resolved;
                },
            );

            return $topicId;
        } catch (\Throwable $e) {
            // Cache/Redis unreachable, or detection exploded past
            // TopicDetectorService's own catch-all. Do not propagate — the
            // pipeline must keep running; developing-story continuation
            // simply stays unavailable for this cluster.
            $this->logger->warning('topic_hash_resolver.degraded', [
                'topic_hash' => $topicHash,
                'signal_id' => $representativeSignal->getId(),
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function detectViaLlm(SourceSignal $signal): ?int
    {
        $results = $this->topicDetector->detectTopics(
            $signal->getTitle(),
            $signal->getRawSummary() ?? '',
        );

        if ($results === []) {
            return null;
        }

        usort(
            $results,
            static fn (array $a, array $b): int => (self::CONFIDENCE_WEIGHTS[$b['confidence']] ?? 0)
                <=> (self::CONFIDENCE_WEIGHTS[$a['confidence']] ?? 0),
        );

        return $results[0]['topicId'];
    }
}
