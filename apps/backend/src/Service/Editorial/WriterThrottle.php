<?php

declare(strict_types=1);

namespace App\Service\Editorial;

use App\Repository\AppSettingRepository;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\Policy\SlidingWindowLimiter;
use Symfony\Component\RateLimiter\RateLimit;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * Global throttle for L3 editorial writers (Sprint 56 T56.06, ADR-022 D2).
 *
 * Caps WriteFlashMessage + WriteDevelopingStoryMessage dispatches at
 * `editorial.throttle.articles_per_hour` per hour across the whole
 * pipeline. Closes the B-H5 false-safety claim — the AppSetting was
 * seeded in S55 (commit 8bf512f) but had zero enforcement until now.
 *
 * Why direct {@see SlidingWindowLimiter} construction instead of the
 * factory autowired by the `editorial_writer` YAML entry?
 *   The YAML factory bakes the limit at container-build time, so tuning
 *   `editorial.throttle.articles_per_hour` via `app:settings:set` would
 *   have required a container rebuild to take effect (useless in an
 *   incident). Direct construction reads the AppSetting per call and
 *   rebuilds the limiter against the shared `cache.rate_limiter` pool,
 *   preserving the hit counts already recorded there so tuning is hot.
 *
 * Key semantics:
 *   - Single global key (`editorial_writer_global`) — the throttle is
 *     pipeline-wide, not per-user/per-topic. Adding dimensions here
 *     would require a matching AppSetting design (out of scope for
 *     T56.06; audit ADR-022 Open Question future work).
 *
 * Ordering in handlers (see {@see \App\MessageHandler\Editorial\WriteFlashMessageHandler}
 * and sibling): {@code emergency_halt → approvedEscalationLogId bypass →
 * throttle → guard chain}. Editor-approved escalations skip the throttle
 * on purpose — the human has accepted responsibility and the audit trail
 * is the EditorialEscalationLog, not the RateLimiter.
 */
readonly class WriterThrottle
{
    private const string APP_SETTING_KEY = 'editorial.throttle.articles_per_hour';
    private const int DEFAULT_LIMIT = 10;

    /**
     * Shared identifier for the pipeline-wide throttle. Changing this
     * invalidates the in-flight sliding window — only do it across a
     * deploy window where the switch is acceptable.
     */
    private const string LIMITER_ID = 'editorial_writer_global';

    public function __construct(
        #[Autowire(service: 'cache.rate_limiter')]
        private CacheItemPoolInterface $cachePool,
        private AppSettingRepository $appSettingRepository,
        private LoggerInterface $logger,
    ) {}

    /**
     * Attempt to consume `$tokens` from the writer rate limit. Returns
     * the PSR `RateLimit` (inspect `->isAccepted()` + `->getRetryAfter()`).
     * The method is side-effect-bounded: it persists one sliding-window
     * hit on the cache backend and may emit a structured log when the
     * call is rejected — no re-dispatch here, the handler owns that.
     */
    public function consume(int $tokens = 1): RateLimit
    {
        $limit = $this->resolveLimit();
        $limiter = new SlidingWindowLimiter(
            id: self::LIMITER_ID,
            limit: $limit,
            interval: new \DateInterval('PT1H'),
            storage: new CacheStorage($this->cachePool),
        );

        $rateLimit = $limiter->consume($tokens);

        if (!$rateLimit->isAccepted()) {
            $this->logger->info('writer_throttle.blocked', [
                'limit' => $rateLimit->getLimit(),
                'retry_after_seconds' => max(0, $rateLimit->getRetryAfter()->getTimestamp() - time()),
            ]);
        }

        return $rateLimit;
    }

    /**
     * Effective limit in use. Exposed for observability + tests; handlers
     * themselves don't need this — the RateLimit returned by
     * {@see self::consume()} already carries `getLimit()`.
     */
    public function currentLimit(): int
    {
        return $this->resolveLimit();
    }

    private function resolveLimit(): int
    {
        $raw = $this->appSettingRepository->get(self::APP_SETTING_KEY);
        if ($raw === null) {
            return self::DEFAULT_LIMIT;
        }

        $parsed = (int) $raw;

        return $parsed > 0 ? $parsed : self::DEFAULT_LIMIT;
    }
}
