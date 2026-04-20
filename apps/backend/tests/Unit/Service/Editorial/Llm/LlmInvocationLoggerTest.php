<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Llm;

use App\Entity\Editorial\LlmAgentCallLog;
use App\Service\Editorial\Llm\LlmInvocationLogger;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit test for {@see LlmInvocationLogger} (Sprint 56 T56.09).
 *
 * Happy path + graceful-degrade contract — observability MUST NOT break
 * the pipeline when the DB is unavailable.
 */
class LlmInvocationLoggerTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testLogInvocationPersistsRowWithAllFieldsAndFlushes(): void
    {
        /** @var LlmAgentCallLog|null $captured */
        $captured = null;
        $this->em->expects($this->once())
            ->method('persist')
            ->willReturnCallback(function (object $entity) use (&$captured): void {
                $this->assertInstanceOf(LlmAgentCallLog::class, $entity);
                $captured = $entity;
            });
        $this->em->expects($this->once())->method('flush');
        $this->logger->expects($this->never())->method('warning');

        $service = new LlmInvocationLogger($this->em, $this->logger);
        $service->logInvocation(
            agentName: 'flash_writer',
            promptHash: str_repeat('d', 64),
            durationMs: 3500,
            inputTokens: 1024,
            outputTokens: 256,
            cacheReadTokens: 128,
            cacheCreationTokens: 64,
            costUsd: 0.012,
            model: 'claude-haiku-4-5',
            verdict: null,
        );

        $this->assertNotNull($captured);
        $this->assertSame('flash_writer', $captured->getAgentName());
        $this->assertSame(str_repeat('d', 64), $captured->getPromptHash());
        $this->assertSame(3500, $captured->getDurationMs());
        $this->assertSame(1024, $captured->getInputTokenCount());
        $this->assertSame(256, $captured->getOutputTokenCount());
        $this->assertSame(128, $captured->getCacheReadTokenCount());
        $this->assertSame(64, $captured->getCacheCreationTokenCount());
        $this->assertSame(0.012, $captured->getCostUsd());
        $this->assertSame('claude-haiku-4-5', $captured->getModel());
        $this->assertNull($captured->getVerdict());
        // ULID: 26 chars, Crockford base32 alphabet
        $this->assertSame(26, \strlen($captured->getInvocationId()));
    }

    public function testLogInvocationDegradesGracefullyOnPersistException(): void
    {
        // Persistence explodes — maybe Postgres is down, maybe the schema
        // is drifted, doesn't matter. Observability must swallow it.
        $this->em->method('persist')
            ->willThrowException(new \RuntimeException('DB connection lost'));
        $this->em->expects($this->never())->method('flush');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('llm_invocation_logger.persist_failed', $this->callback(static function (array $ctx): bool {
                return ($ctx['agent_name'] ?? null) === 'flash_writer'
                    && ($ctx['prompt_hash'] ?? null) === str_repeat('e', 64)
                    && str_contains((string) ($ctx['error'] ?? ''), 'DB connection lost');
            }));

        $service = new LlmInvocationLogger($this->em, $this->logger);

        // Must NOT rethrow — the LLM call already succeeded; whether we can
        // record its metrics is a separate concern.
        $service->logInvocation(
            agentName: 'flash_writer',
            promptHash: str_repeat('e', 64),
            durationMs: 1500,
            inputTokens: 0,
            outputTokens: 0,
        );
    }

    public function testLogInvocationDegradesGracefullyOnFlushException(): void
    {
        // Persist succeeds, flush fails (constraint violation, timeout, …).
        // Same contract: warn + swallow.
        $this->em->method('flush')
            ->willThrowException(new \RuntimeException('deadlock detected'));

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('llm_invocation_logger.persist_failed', $this->anything());

        $service = new LlmInvocationLogger($this->em, $this->logger);

        $service->logInvocation(
            agentName: 'legal_guard',
            promptHash: str_repeat('f', 64),
            durationMs: 900,
            inputTokens: 500,
            outputTokens: 200,
            verdict: 'fail',
        );
    }

    public function testUlidIsUniquePerInvocation(): void
    {
        // Regression guard: a bug where the logger reused a single ULID
        // would make dedup analysis useless. Log 5 times, assert 5 distinct
        // invocation ids.
        $ulidsCaptured = [];
        $this->em->method('persist')
            ->willReturnCallback(function (object $entity) use (&$ulidsCaptured): void {
                $this->assertInstanceOf(LlmAgentCallLog::class, $entity);
                $ulidsCaptured[] = $entity->getInvocationId();
            });

        $service = new LlmInvocationLogger($this->em, $this->logger);
        for ($i = 0; $i < 5; ++$i) {
            $service->logInvocation(
                agentName: 'flash_writer',
                promptHash: str_repeat('a', 64),
                durationMs: 1000,
                inputTokens: 10,
                outputTokens: 5,
            );
        }

        $this->assertCount(5, array_unique($ulidsCaptured));
    }
}
