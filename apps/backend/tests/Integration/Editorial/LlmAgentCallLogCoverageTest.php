<?php

declare(strict_types=1);

namespace App\Tests\Integration\Editorial;

use App\Entity\Editorial\LlmAgentCallLog;
use App\Repository\Editorial\LlmAgentCallLogRepository;
use App\Service\Editorial\Llm\LlmInvocationLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration test for T57.03 (ADR-023 D2) — W' baseline + P' attachVerdict
 * end-to-end through the real database.
 *
 * Verifies the contract against real Doctrine + PostgreSQL rather than mocks:
 *   - logInvocation persists a row and returns a usable ULID
 *   - attachVerdict locates the row by invocation_id (the UNIQUE index added
 *     in Version20260420101032 is exercised implicitly via findOneBy)
 *   - the UPDATE surfaces the new verdict on subsequent reads
 *   - attachVerdict no-ops on a missing ULID without throwing
 *
 * Uses DAMA DoctrineTestBundle auto-rollback (per phpunit.dist.xml extensions)
 * so each test runs in a clean transaction.
 */
final class LlmAgentCallLogCoverageTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private LlmInvocationLogger $logger;
    private LlmAgentCallLogRepository $repository;

    /** @var list<string> ULIDs persisted during this test — cleaned up in tearDown. */
    private array $ulidsToClean = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->em = $container->get('doctrine')->getManager();
        $this->logger = $container->get(LlmInvocationLogger::class);
        $this->repository = $container->get(LlmAgentCallLogRepository::class);
    }

    protected function tearDown(): void
    {
        // DAMA DoctrineTestBundle is NOT registered in this project (bundles.php
        // check, 2026-04-20) so tests must clean up their own fixtures the same
        // way LlmAgentCallLogRepositoryTest does.
        if ($this->ulidsToClean !== []) {
            $conn = $this->em->getConnection();
            foreach ($this->ulidsToClean as $ulid) {
                $conn->executeStatement(
                    'DELETE FROM llm_agent_call_log WHERE invocation_id = :ulid',
                    ['ulid' => $ulid],
                );
            }
        }

        $this->em->close();
        parent::tearDown();
    }

    /** Helper: records a ULID for tearDown cleanup and returns it. */
    private function track(?string $ulid): ?string
    {
        if ($ulid !== null) {
            $this->ulidsToClean[] = $ulid;
        }

        return $ulid;
    }

    public function testLogInvocationPersistsRowReturnsUlidAndRowReadsBack(): void
    {
        $ulid = $this->track($this->logger->logInvocation(
            agentName: 'flash_writer',
            promptHash: str_repeat('a', 64),
            durationMs: 1500,
            inputTokens: 500,
            outputTokens: 120,
            model: 'claude-haiku-4-5',
            verdict: null,
        ));

        self::assertNotNull($ulid, 'logInvocation must return the generated ULID');
        self::assertSame(26, strlen($ulid), 'ULID must be 26 chars');

        $this->em->clear(); // force a fresh read from the DB
        $row = $this->repository->findOneBy(['invocationId' => $ulid]);

        self::assertNotNull($row, 'row must be retrievable via the UNIQUE invocation_id index');
        self::assertSame('flash_writer', $row->getAgentName());
        self::assertNull($row->getVerdict(), 'baseline row starts with verdict=null');
        self::assertSame(1500, $row->getDurationMs());
    }

    public function testAttachVerdictUpdatesRowInPlace(): void
    {
        $ulid = $this->track($this->logger->logInvocation(
            agentName: 'legal_guard',
            promptHash: str_repeat('b', 64),
            durationMs: 2200,
            inputTokens: 800,
            outputTokens: 300,
        ));
        self::assertNotNull($ulid);

        $this->logger->attachVerdict($ulid, 'escalate_cat6');

        $this->em->clear();
        $row = $this->repository->findOneBy(['invocationId' => $ulid]);

        self::assertNotNull($row);
        self::assertSame('escalate_cat6', $row->getVerdict());
        self::assertSame('legal_guard', $row->getAgentName());
    }

    public function testAttachVerdictNoOpsOnMissingUlidWithoutThrowing(): void
    {
        // Degraded path: the baseline persist failed earlier in the pipeline,
        // but a gate still tries to attach its verdict. Logger must swallow
        // the miss — observability never breaks the pipeline.
        $this->logger->attachVerdict('01JFXXXXXXXXXXXXXXXXXXXXXX', 'pass');

        $this->em->clear();
        $row = $this->repository->findOneBy(['invocationId' => '01JFXXXXXXXXXXXXXXXXXXXXXX']);

        self::assertNull($row, 'no ghost row should be created');
    }

    public function testEndToEndSixAgentCycleProducesSixDistinctRows(): void
    {
        // Mirrors the editorial pipeline's agent fan-out for a single article:
        // 1× flash_writer (writer, no verdict) +
        // 4× gates (legal/style/escalation/verification) +
        // 1× context (writer).
        $invocations = [
            ['flash_writer', null],
            ['legal_guard', 'pass'],
            ['style_guard', 'pass'],
            ['escalation_classifier', 'none'],
            ['verification_gate', 'full_flash'],
            ['context', null],
        ];

        $ulids = [];
        foreach ($invocations as [$agent, $verdict]) {
            $ulid = $this->track($this->logger->logInvocation(
                agentName: $agent,
                promptHash: hash('sha256', $agent),
                durationMs: 1000,
                inputTokens: 100,
                outputTokens: 50,
            ));
            self::assertNotNull($ulid);
            $ulids[$agent] = $ulid;

            if ($verdict !== null) {
                $this->logger->attachVerdict($ulid, $verdict);
            }
        }

        self::assertCount(6, array_unique($ulids), 'all 6 ULIDs must be distinct');

        $this->em->clear();
        foreach ($invocations as [$agent, $verdict]) {
            $row = $this->repository->findOneBy(['invocationId' => $ulids[$agent]]);
            self::assertNotNull($row, "row for {$agent} must exist");
            self::assertSame($agent, $row->getAgentName());
            self::assertSame($verdict, $row->getVerdict(), "verdict for {$agent}");
        }
    }
}
