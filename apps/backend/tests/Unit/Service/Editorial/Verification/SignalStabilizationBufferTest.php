<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Verification;

use App\Repository\AppSettingRepository;
use App\Service\Editorial\Verification\SignalStabilizationBuffer;
use App\Tests\Support\InMemoryRedisStub;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class SignalStabilizationBufferTest extends TestCase
{
    private InMemoryRedisStub $redis;
    private AppSettingRepository&MockObject $settings;
    private SignalStabilizationBuffer $buffer;

    protected function setUp(): void
    {
        $this->redis = new InMemoryRedisStub();
        $this->settings = $this->createMock(AppSettingRepository::class);
        $this->settings->method('getInt')
            ->with('editorial.stabilization_window_seconds', 45)
            ->willReturn(45);

        $this->buffer = new SignalStabilizationBuffer(
            $this->redis,
            $this->settings,
            new NullLogger(),
        );
    }

    public function testAddRegistersSignalUnderTopicHash(): void
    {
        $now = new \DateTimeImmutable('2026-04-18 12:00:00');

        $this->buffer->add('topic-abc', 101, $now);

        $members = $this->redis->zrangebyscore(
            SignalStabilizationBuffer::KEY_PREFIX . 'topic-abc',
            '-inf',
            '+inf',
        );

        $this->assertSame(['101'], $members);
        $this->assertSame(
            SignalStabilizationBuffer::BUCKET_TTL_SECONDS,
            $this->redis->getTtl(SignalStabilizationBuffer::KEY_PREFIX . 'topic-abc'),
        );
    }

    public function testFlushStabilizedReturnsEmptyWhenNothingAged(): void
    {
        $now = new \DateTimeImmutable('2026-04-18 12:00:00');
        $this->buffer->add('topic-fresh', 1, $now);

        // Flush at now (window=45s → threshold = now - 45s). Signal at now
        // is not yet stabilized, should remain.
        $result = $this->buffer->flushStabilized($now);

        $this->assertSame([], $result);

        $remaining = $this->redis->zrangebyscore(
            SignalStabilizationBuffer::KEY_PREFIX . 'topic-fresh',
            '-inf',
            '+inf',
        );
        $this->assertSame(['1'], $remaining);
    }

    public function testFlushStabilizedReturnsAgedSignalsAndRemovesThem(): void
    {
        $captured = new \DateTimeImmutable('2026-04-18 12:00:00');
        $this->buffer->add('topic-old', 42, $captured);
        $this->buffer->add('topic-old', 43, $captured);

        // Now 60s later: 60 > 45s window, signals are stabilized.
        $now = $captured->modify('+60 seconds');

        $flushed = $this->buffer->flushStabilized($now);

        $this->assertSame(['topic-old' => [42, 43]], $flushed);

        $remaining = $this->redis->zrangebyscore(
            SignalStabilizationBuffer::KEY_PREFIX . 'topic-old',
            '-inf',
            '+inf',
        );
        $this->assertSame([], $remaining, 'Flushed members should be removed from the bucket.');
    }

    public function testFlushStabilizedHandlesMultipleBuckets(): void
    {
        $captured = new \DateTimeImmutable('2026-04-18 12:00:00');
        $this->buffer->add('topic-a', 1, $captured);
        $this->buffer->add('topic-b', 2, $captured);
        $this->buffer->add('topic-c', 3, $captured);

        $now = $captured->modify('+50 seconds');

        $flushed = $this->buffer->flushStabilized($now);

        $this->assertCount(3, $flushed);
        $this->assertArrayHasKey('topic-a', $flushed);
        $this->assertArrayHasKey('topic-b', $flushed);
        $this->assertArrayHasKey('topic-c', $flushed);
        $this->assertSame([1], $flushed['topic-a']);
    }

    public function testFlushStabilizedPartialBucket(): void
    {
        // Two signals in the same bucket, different capture times.
        // Only the older one has aged past the window.
        $old = new \DateTimeImmutable('2026-04-18 12:00:00');
        $fresh = $old->modify('+30 seconds');

        $this->buffer->add('topic-mixed', 10, $old);
        $this->buffer->add('topic-mixed', 11, $fresh);

        // now = old + 60s → old(0s) aged 60s (>45s), fresh(30s) aged 30s (<45s).
        $now = $old->modify('+60 seconds');

        $flushed = $this->buffer->flushStabilized($now);

        $this->assertSame(['topic-mixed' => [10]], $flushed);

        $remaining = $this->redis->zrangebyscore(
            SignalStabilizationBuffer::KEY_PREFIX . 'topic-mixed',
            '-inf',
            '+inf',
        );
        $this->assertSame(['11'], $remaining);
    }

    public function testAddIsIdempotentOnSameSignal(): void
    {
        $t1 = new \DateTimeImmutable('2026-04-18 12:00:00');
        $t2 = $t1->modify('+10 seconds');

        $this->buffer->add('topic-dup', 99, $t1);
        $this->buffer->add('topic-dup', 99, $t2);

        // Only one member, with the refreshed score (t2).
        $count = \count($this->redis->zrangebyscore(
            SignalStabilizationBuffer::KEY_PREFIX . 'topic-dup',
            '-inf',
            '+inf',
        ));
        $this->assertSame(1, $count);

        // At now = t1 + 50s (40s after t2 refresh), window=45s →
        // signal has only aged 40s → NOT stabilized.
        $now = $t1->modify('+50 seconds');
        $this->assertSame([], $this->buffer->flushStabilized($now));

        // At now = t1 + 60s (50s after t2 refresh) → stabilized.
        $now2 = $t1->modify('+60 seconds');
        $this->assertSame(['topic-dup' => [99]], $this->buffer->flushStabilized($now2));
    }
}
