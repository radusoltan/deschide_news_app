<?php

declare(strict_types=1);

namespace App\Service\Editorial\Verification;

use App\Repository\AppSettingRepository;
use Predis\Client as PredisClient;
use Psr\Log\LoggerInterface;

/**
 * Redis-backed topic-level stabilization window for editorial signals
 * (Sprint 54 T54.6, ADR-020 D2 amendment 2026-04-18).
 *
 * Signals flow into Redis sorted sets keyed by `editorial:stabilization:{topicHash}`,
 * with the capture timestamp (unix seconds) as score and the signal id as
 * member. Once a signal has been sitting in the set for at least
 * `editorial.stabilization_window_seconds` (default 45s), it is considered
 * stabilized — the claim has had enough time to either attract
 * corroborating signals or fade.
 *
 * The `FlushStabilizedSignalsMessage` tick (every 10s) calls
 * {@see flushStabilized()} which scans all stabilization buckets, yanks
 * the aged-out members atomically, and returns them grouped by topic hash
 * so the caller can dispatch an {@see \App\Message\Editorial\AggregateSignalsMessage}
 * per cluster.
 *
 * This is the first direct consumer of `Predis\Client` in the editorial
 * layer (Analytics uses it for stats). The stabilization data is
 * deliberately NOT in a Symfony cache pool because it isn't cache — it
 * is ephemeral state with deterministic TTL + ordered consumption.
 */
class SignalStabilizationBuffer
{
    /** Redis key prefix so stabilization sets don't collide with other app state. */
    public const KEY_PREFIX = 'editorial:stabilization:';

    /**
     * Hard TTL on each stabilization bucket (2h). Generous upper bound so a
     * signal whose cluster never stabilizes (e.g. pipeline paused mid-run)
     * eventually vanishes and does not leak memory.
     */
    public const BUCKET_TTL_SECONDS = 7200;

    /** How many keys per SCAN step — Redis best-practice for high cardinality. */
    private const SCAN_COUNT = 100;

    public function __construct(
        private readonly PredisClient $redis,
        private readonly AppSettingRepository $settings,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Register a signal under a topic hash. Idempotent on `(topicHash, signalId)`
     * — re-adding an existing signal refreshes its score to the current
     * captured_at and resets the bucket TTL.
     */
    public function add(string $topicHash, int $signalId, \DateTimeImmutable $capturedAt): void
    {
        $key = self::KEY_PREFIX . $topicHash;
        $this->redis->zadd($key, [(string) $signalId => (float) $capturedAt->getTimestamp()]);
        $this->redis->expire($key, self::BUCKET_TTL_SECONDS);
    }

    /**
     * Scan all stabilization buckets, pop signals whose captured_at is older
     * than `now - stabilization_window_seconds`, and return them grouped by
     * topic hash.
     *
     * Returns [] when no cluster has stabilized. Callers are expected to
     * dispatch an AggregateSignalsMessage per topic hash in the return map.
     *
     * @return array<string, list<int>> topicHash => signalIds[]
     */
    public function flushStabilized(?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();
        $windowSeconds = max(1, $this->settings->getInt('editorial.stabilization_window_seconds', 45));
        $threshold = (float) ($now->getTimestamp() - $windowSeconds);

        $flushed = [];
        $cursor = '0';

        do {
            /** @var array{0: string, 1: list<string>} $scanResult */
            $scanResult = $this->redis->scan($cursor, [
                'MATCH' => self::KEY_PREFIX . '*',
                'COUNT' => self::SCAN_COUNT,
            ]);

            $cursor = $scanResult[0];
            $keys = $scanResult[1];

            foreach ($keys as $key) {
                /** @var list<string> $members */
                $members = $this->redis->zrangebyscore($key, '-inf', (string) $threshold);
                if ($members === []) {
                    continue;
                }

                $this->redis->zrem($key, ...$members);

                $topicHash = substr($key, \strlen(self::KEY_PREFIX));
                $flushed[$topicHash] = array_map('intval', $members);
            }
        } while ($cursor !== '0');

        if ($flushed !== []) {
            $this->logger->info('SignalStabilizationBuffer: flushed stabilized clusters', [
                'clusters' => \count($flushed),
                'signals_total' => array_sum(array_map('\count', $flushed)),
                'window_seconds' => $windowSeconds,
            ]);
        }

        return $flushed;
    }
}
