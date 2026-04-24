<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Predis\Client as PredisClient;

/**
 * Minimal in-memory stand-in for {@see PredisClient}, covering only the
 * Redis commands used by the editorial verification layer:
 *   - zadd(key, [member => score])
 *   - expire(key, ttl)
 *   - zrangebyscore(key, min, max)
 *   - zrem(key, member, ...)
 *   - scan(cursor, ['MATCH' => pattern, 'COUNT' => n])
 *
 * Behavioural parity with the real Redis is sufficient for deterministic
 * unit tests. Not suitable as a production replacement.
 *
 * Predis routes command invocations through `__call` → `createCommand` →
 * `executeCommand`; overriding `__call` here fully bypasses the transport
 * layer without touching Predis's connection state.
 */
final class InMemoryRedisStub extends PredisClient
{
    /** @var array<string, array<string, float>> */
    private array $sortedSets = [];

    /** @var array<string, int> */
    private array $ttls = [];

    public function __construct()
    {
        // Skip parent __construct — no Redis connection, no profile needed.
    }

    public function __call($commandID, $arguments)
    {
        $method = strtolower((string) $commandID);

        return match ($method) {
            'zadd' => $this->doZadd(...$arguments),
            'expire' => $this->doExpire(...$arguments),
            'zrangebyscore' => $this->doZrangebyscore(...$arguments),
            'zrem' => $this->doZrem(...$arguments),
            'scan' => $this->doScan(...$arguments),
            default => throw new \LogicException(sprintf(
                'InMemoryRedisStub: unsupported Redis command "%s" — extend the stub if this is legitimate.',
                $method,
            )),
        };
    }

    /** Introspection helper for tests — real Redis doesn't expose this via command. */
    public function getTtl(string $key): ?int
    {
        return $this->ttls[$key] ?? null;
    }

    /**
     * @param array<string, mixed> $membersScores member => score
     */
    private function doZadd(string $key, array $membersScores): int
    {
        $this->sortedSets[$key] ??= [];
        $added = 0;

        foreach ($membersScores as $member => $score) {
            $memberKey = (string) $member;
            if (!isset($this->sortedSets[$key][$memberKey])) {
                ++$added;
            }
            $this->sortedSets[$key][$memberKey] = (float) $score;
        }

        return $added;
    }

    private function doExpire(string $key, int $ttl): int
    {
        if (!isset($this->sortedSets[$key])) {
            return 0;
        }
        $this->ttls[$key] = $ttl;

        return 1;
    }

    /**
     * @return list<string>
     */
    private function doZrangebyscore(string $key, string $min, string $max): array
    {
        if (!isset($this->sortedSets[$key])) {
            return [];
        }

        $minF = $min === '-inf' ? -\INF : (float) $min;
        $maxF = $max === '+inf' ? \INF : (float) $max;

        // Preserve ascending score order (Redis semantics).
        $pairs = $this->sortedSets[$key];
        asort($pairs);

        $out = [];
        foreach ($pairs as $member => $score) {
            if ($score >= $minF && $score <= $maxF) {
                $out[] = (string) $member;
            }
        }

        return $out;
    }

    private function doZrem(string $key, string ...$members): int
    {
        if (!isset($this->sortedSets[$key])) {
            return 0;
        }

        $removed = 0;
        foreach ($members as $member) {
            if (isset($this->sortedSets[$key][$member])) {
                unset($this->sortedSets[$key][$member]);
                ++$removed;
            }
        }

        if ($this->sortedSets[$key] === []) {
            unset($this->sortedSets[$key]);
        }

        return $removed;
    }

    /**
     * Stub SCAN always returns all matching keys in a single pass (cursor=0).
     * Real Redis paginates, but for our bucket counts in S54 smoke (hundreds)
     * a single page is realistic and the contract on callers is identical.
     *
     * @param array<string, mixed> $options
     *
     * @return array{0: string, 1: list<string>}
     */
    private function doScan(string $cursor, array $options = []): array
    {
        $pattern = (string) ($options['MATCH'] ?? '*');
        $regex = $this->patternToRegex($pattern);

        $keys = [];
        foreach (array_keys($this->sortedSets) as $key) {
            if (preg_match($regex, $key) === 1) {
                $keys[] = $key;
            }
        }

        return ['0', $keys];
    }

    private function patternToRegex(string $pattern): string
    {
        $escaped = preg_quote($pattern, '/');
        $escaped = str_replace(['\\*', '\\?'], ['.*', '.'], $escaped);

        return '/^' . $escaped . '$/';
    }
}
