<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial;

use App\Entity\Editorial\SourceSignal;
use App\Service\Editorial\TopicHashResolver;
use App\Service\TopicDetectorService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Unit test for {@see TopicHashResolver} (Sprint 56 T56.04).
 *
 * Strategy: use Symfony's in-memory {@see ArrayAdapter} (implements
 * {@see CacheInterface}) for the happy paths so we exercise the real PSR-6
 * machinery around `$cache->get(..., $compute)`, and mock
 * {@see TopicDetectorService} to control LLM responses deterministically.
 * The degraded path uses a mock cache that throws — the only way to prove
 * we swallow the failure instead of leaking it into the handler.
 */
class TopicHashResolverTest extends TestCase
{
    private TopicDetectorService&MockObject $topicDetector;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->topicDetector = $this->createMock(TopicDetectorService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testCacheHitReturnsTopicIdWithoutLlmCall(): void
    {
        $cache = new ArrayAdapter();
        // Pre-seed positive resolution on the key the resolver builds.
        $item = $cache->getItem('topic_hash_map.abc123');
        $item->set(42);
        $cache->save($item);

        // LLM must never be invoked on a cache hit.
        $this->topicDetector->expects($this->never())->method('detectTopics');

        $resolver = new TopicHashResolver($this->topicDetector, $cache, $this->logger);

        $result = $resolver->resolve('abc123', $this->signal(1, 'Title', 'Summary'));

        $this->assertSame(42, $result);
    }

    public function testCacheHitReturnsNullForCachedNegative(): void
    {
        $cache = new ArrayAdapter();
        $item = $cache->getItem('topic_hash_map.unresolvable');
        $item->set(null);
        $cache->save($item);

        $this->topicDetector->expects($this->never())->method('detectTopics');

        $resolver = new TopicHashResolver($this->topicDetector, $cache, $this->logger);

        $result = $resolver->resolve('unresolvable', $this->signal(2, 'Title', 'Summary'));

        $this->assertNull($result);
    }

    public function testCacheMissInvokesLlmPersistsWithSevenDayTtl(): void
    {
        $cache = new ArrayAdapter();
        $signal = $this->signal(100, 'Moldova semnează tratat cu UE', 'Președintele a participat la ceremonie');

        $this->topicDetector->expects($this->once())
            ->method('detectTopics')
            ->with('Moldova semnează tratat cu UE', 'Președintele a participat la ceremonie')
            ->willReturn([
                ['topicId' => 7, 'confidence' => 'high', 'reason' => 'Tratat UE-Moldova'],
            ]);

        // Must emit the `topic_hash_resolver.miss` log line with positive TTL.
        $this->logger->expects($this->once())
            ->method('info')
            ->with('topic_hash_resolver.miss', $this->callback(static function (array $ctx): bool {
                return ($ctx['topic_hash'] ?? null) === 'hash-positive'
                    && ($ctx['topic_id'] ?? null) === 7
                    && ($ctx['signal_id'] ?? null) === 100
                    && ($ctx['ttl_seconds'] ?? null) === 604800;
            }));

        $resolver = new TopicHashResolver($this->topicDetector, $cache, $this->logger);

        $first = $resolver->resolve('hash-positive', $signal);
        $this->assertSame(7, $first);

        // Second call must be served from cache — detect mock was
        // `expects($this->once())`, so a second LLM invocation would fail
        // this assertion path.
        $second = $resolver->resolve('hash-positive', $signal);
        $this->assertSame(7, $second);
    }

    public function testCacheMissInvokesLlmCachesNegativeWithOneHourTtl(): void
    {
        $cache = new ArrayAdapter();
        $signal = $this->signal(200, 'Totally irrelevant signal', null);

        $this->topicDetector->expects($this->once())
            ->method('detectTopics')
            ->willReturn([]); // LLM: no topics match

        $this->logger->expects($this->once())
            ->method('info')
            ->with('topic_hash_resolver.miss', $this->callback(static function (array $ctx): bool {
                return ($ctx['topic_hash'] ?? null) === 'hash-negative'
                    && ($ctx['topic_id'] ?? null) === null
                    && ($ctx['ttl_seconds'] ?? null) === 3600;
            }));

        $resolver = new TopicHashResolver($this->topicDetector, $cache, $this->logger);

        $this->assertNull($resolver->resolve('hash-negative', $signal));
        // Second call served from cache (LLM was expects->once, not again).
        $this->assertNull($resolver->resolve('hash-negative', $signal));
    }

    public function testHighestConfidenceTopicSelectedWhenMultipleReturned(): void
    {
        $cache = new ArrayAdapter();
        $signal = $this->signal(300, 'Title', 'Summary');

        $this->topicDetector->method('detectTopics')->willReturn([
            ['topicId' => 5, 'confidence' => 'low',    'reason' => 'weak'],
            ['topicId' => 9, 'confidence' => 'high',   'reason' => 'dominant'],
            ['topicId' => 7, 'confidence' => 'medium', 'reason' => 'moderate'],
        ]);

        $resolver = new TopicHashResolver($this->topicDetector, $cache, $this->logger);

        $this->assertSame(9, $resolver->resolve('hash-multi', $signal));
    }

    public function testCacheExceptionDegradesGracefullyWithWarning(): void
    {
        // Mock cache whose ->get() blows up. This simulates Redis outage or
        // a corrupt cache item — the whole point of the T56.04 degrade clause.
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willThrowException(new \RuntimeException('redis unreachable'));

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('topic_hash_resolver.degraded', $this->callback(static function (array $ctx): bool {
                return ($ctx['topic_hash'] ?? null) === 'hash-broken-cache'
                    && ($ctx['error'] ?? null) === 'redis unreachable';
            }));

        // LLM must NOT be invoked through the degraded branch — the
        // exception bubbles up from `->get()` before the compute callback
        // runs.
        $this->topicDetector->expects($this->never())->method('detectTopics');

        $resolver = new TopicHashResolver($this->topicDetector, $cache, $this->logger);

        $result = $resolver->resolve('hash-broken-cache', $this->signal(400, 'Title', 'Summary'));

        $this->assertNull($result);
    }

    private function signal(int $id, string $title, ?string $rawSummary): SourceSignal&MockObject
    {
        $signal = $this->createMock(SourceSignal::class);
        $signal->method('getId')->willReturn($id);
        $signal->method('getTitle')->willReturn($title);
        $signal->method('getRawSummary')->willReturn($rawSummary);

        return $signal;
    }
}
