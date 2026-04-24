<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial\Llm;

use App\Entity\Editorial\LlmAgentCallLog;
use App\Repository\Editorial\LlmAgentCallLogRepository;
use App\Service\Editorial\Llm\LlmInvocationLogger;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * Unit test for {@see LlmInvocationLogger} (Sprint 56 T56.09, extended T57.03).
 *
 * Happy path + graceful-degrade contract — observability MUST NOT break
 * the pipeline when the DB is unavailable.
 */
class LlmInvocationLoggerTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private LoggerInterface&MockObject $logger;
    private LlmAgentCallLogRepository&MockObject $repository;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->repository = $this->createMock(LlmAgentCallLogRepository::class);
    }

    private function makeService(): LlmInvocationLogger
    {
        return new LlmInvocationLogger($this->em, $this->logger, $this->repository);
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

        $service = $this->makeService();
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

        $service = $this->makeService();

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

        $service = $this->makeService();

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

        $service = $this->makeService();
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

    public function testLogInvocationReturnsUlidOnSuccess(): void
    {
        // T57.03 — logInvocation now returns the generated ULID so callers
        // can thread it through (executor → gates for attachVerdict).
        $this->em->method('persist');
        $this->em->method('flush');

        $service = $this->makeService();
        $ulid = $service->logInvocation(
            agentName: 'flash_writer',
            promptHash: str_repeat('b', 64),
            durationMs: 100,
            inputTokens: 1,
            outputTokens: 1,
        );

        $this->assertIsString($ulid);
        $this->assertSame(26, \strlen($ulid));
    }

    public function testLogInvocationReturnsNullOnPersistFailure(): void
    {
        // T57.03 — null return signals the caller that attachVerdict would
        // have nothing to UPDATE. Downstream gates gate their verdict-attach
        // call on `$invocationId !== null`.
        $this->em->method('persist')->willThrowException(new \RuntimeException('db down'));

        $service = $this->makeService();
        $ulid = $service->logInvocation(
            agentName: 'flash_writer',
            promptHash: str_repeat('c', 64),
            durationMs: 100,
            inputTokens: 1,
            outputTokens: 1,
        );

        $this->assertNull($ulid);
    }

    public function testAttachVerdictUpdatesExistingRow(): void
    {
        $row = new LlmAgentCallLog(
            agentName: 'legal_guard',
            invocationId: '01JFXXXXXXXXXXXXXXXXXXXXXX',
            promptHash: str_repeat('d', 64),
            durationMs: 1000,
            inputTokenCount: 100,
            outputTokenCount: 50,
        );
        $this->repository->expects($this->once())
            ->method('findOneBy')
            ->with(['invocationId' => '01JFXXXXXXXXXXXXXXXXXXXXXX'])
            ->willReturn($row);
        $this->em->expects($this->once())->method('flush');

        $this->makeService()->attachVerdict('01JFXXXXXXXXXXXXXXXXXXXXXX', 'escalate_cat6');

        $this->assertSame('escalate_cat6', $row->getVerdict());
    }

    public function testAttachVerdictWarnsOnMissingRowWithoutThrowing(): void
    {
        // Graceful degrade: if the baseline row isn't there (e.g. persist
        // failed earlier), attachVerdict no-ops with a warning — it must
        // not break the pipeline.
        $this->repository->method('findOneBy')->willReturn(null);
        $this->em->expects($this->never())->method('flush');
        $this->logger->expects($this->once())
            ->method('warning')
            ->with('llm_invocation_logger.attach_verdict_no_row', $this->anything());

        $this->makeService()->attachVerdict('01JFMISSINGXXXXXXXXXXXXXXX', 'pass');
    }

    public function testAttachVerdictWarnsOnFlushFailureWithoutThrowing(): void
    {
        $row = new LlmAgentCallLog(
            agentName: 'style_guard',
            invocationId: '01JFXXXXXXXXXXXXXXXXXXXXXX',
            promptHash: str_repeat('e', 64),
            durationMs: 500,
            inputTokenCount: 50,
            outputTokenCount: 20,
        );
        $this->repository->method('findOneBy')->willReturn($row);
        $this->em->method('flush')->willThrowException(new \RuntimeException('deadlock'));

        $this->logger->expects($this->once())
            ->method('warning')
            ->with('llm_invocation_logger.attach_verdict_failed', $this->anything());

        // Must NOT rethrow.
        $this->makeService()->attachVerdict('01JFXXXXXXXXXXXXXXXXXXXXXX', 'block');
    }
}
