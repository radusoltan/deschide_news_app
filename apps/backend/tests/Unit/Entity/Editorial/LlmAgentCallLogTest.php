<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity\Editorial;

use App\Entity\Editorial\LlmAgentCallLog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

/**
 * Unit test for the {@see LlmAgentCallLog} entity (Sprint 56 T56.09).
 *
 * Entity is a write-once-read-many log with no setters; tests assert
 * constructor wiring and getter fidelity. Persistence-level concerns
 * (indexes, schema sync) are covered by the repository test + the
 * Doctrine migration run by DevReset.
 */
class LlmAgentCallLogTest extends TestCase
{
    public function testConstructorWiresAllFieldsAndGettersReturnThem(): void
    {
        $invocationId = (string) new Ulid();
        $createdAt = new \DateTimeImmutable('2026-04-20 10:15:00');

        $row = new LlmAgentCallLog(
            agentName: 'flash_writer',
            invocationId: $invocationId,
            promptHash: str_repeat('a', 64),
            durationMs: 1234,
            inputTokenCount: 2048,
            outputTokenCount: 512,
            cacheReadTokenCount: 128,
            cacheCreationTokenCount: 64,
            costUsd: 0.0175,
            model: 'claude-haiku-4-5',
            verdict: null,
            createdAt: $createdAt,
        );

        $this->assertNull($row->getId(), 'id is null until persist/flush assigns it');
        $this->assertSame('flash_writer', $row->getAgentName());
        $this->assertSame($invocationId, $row->getInvocationId());
        $this->assertSame(str_repeat('a', 64), $row->getPromptHash());
        $this->assertSame(1234, $row->getDurationMs());
        $this->assertSame(2048, $row->getInputTokenCount());
        $this->assertSame(512, $row->getOutputTokenCount());
        $this->assertSame(128, $row->getCacheReadTokenCount());
        $this->assertSame(64, $row->getCacheCreationTokenCount());
        $this->assertSame(0.0175, $row->getCostUsd());
        $this->assertSame('claude-haiku-4-5', $row->getModel());
        $this->assertNull($row->getVerdict());
        $this->assertSame($createdAt, $row->getCreatedAt());
    }

    public function testDefaultsSensibleForGeminiFallbackPath(): void
    {
        // The Gemini CLI wrapper does not expose token / cost metrics,
        // so the hook passes explicit zeros. The constructor must accept
        // this without coercing to negative numbers or complaining.
        $row = new LlmAgentCallLog(
            agentName: 'flash_writer',
            invocationId: (string) new Ulid(),
            promptHash: str_repeat('b', 64),
            durationMs: 9800,
            inputTokenCount: 0,
            outputTokenCount: 0,
        );

        $this->assertSame(0, $row->getInputTokenCount());
        $this->assertSame(0, $row->getOutputTokenCount());
        $this->assertSame(0, $row->getCacheReadTokenCount());
        $this->assertSame(0, $row->getCacheCreationTokenCount());
        $this->assertSame(0.0, $row->getCostUsd());
        $this->assertNull($row->getModel());
        $this->assertNull($row->getVerdict());
        // createdAt not passed — constructor must default to now.
        $this->assertLessThanOrEqual(
            (new \DateTimeImmutable())->getTimestamp(),
            $row->getCreatedAt()->getTimestamp(),
        );
    }
}
