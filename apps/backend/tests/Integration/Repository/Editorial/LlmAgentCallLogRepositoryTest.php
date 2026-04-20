<?php

declare(strict_types=1);

namespace App\Tests\Integration\Repository\Editorial;

use App\Entity\Editorial\LlmAgentCallLog;
use App\Repository\Editorial\LlmAgentCallLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Ulid;

/**
 * T56.09 — LlmAgentCallLogRepository integration tests (real DB).
 *
 * Drives DQL against the real schema so the aggregation paths (GROUP BY
 * verdict, time-range BETWEEN, etc.) are exercised end-to-end — unit mocks
 * would let a DQL typo slide through.
 */
class LlmAgentCallLogRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private LlmAgentCallLogRepository $repository;

    /** @var list<int> */
    private array $idsToClean = [];

    protected function setUp(): void
    {
        self::bootKernel();
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get('doctrine')->getManager();
        $this->em = $em;
        // Repositories are not auto-exposed as public services in the test
        // container; fetch via Doctrine's own resolver which returns the
        // configured ServiceEntityRepository instance. The PHPDoc on
        // `$em->getRepository($class)` already carries the covariant return
        // type, so no extra cast or assert is required.
        $this->repository = $em->getRepository(LlmAgentCallLog::class);
    }

    protected function tearDown(): void
    {
        foreach ($this->idsToClean as $id) {
            $this->em->getConnection()->executeStatement(
                'DELETE FROM llm_agent_call_log WHERE id = :id',
                ['id' => $id],
            );
        }

        $this->em->close();
        parent::tearDown();
    }

    public function testFindByAgentAndTimeRangeFiltersByAgentAndWindow(): void
    {
        $inWindowEarly = $this->seed('flash_writer', new \DateTimeImmutable('-3 hours'));
        $inWindowLate = $this->seed('flash_writer', new \DateTimeImmutable('-30 minutes'));
        $outOfWindow = $this->seed('flash_writer', new \DateTimeImmutable('-25 hours'));
        $otherAgent = $this->seed('legal_guard_haiku', new \DateTimeImmutable('-1 hour'));

        $results = $this->repository->findByAgentAndTimeRange(
            'flash_writer',
            new \DateTimeImmutable('-4 hours'),
            new \DateTimeImmutable('+1 minute'),
        );

        // Two rows in range for flash_writer — ordered ASC by created_at.
        $this->assertCount(2, $results);
        $this->assertSame($inWindowEarly->getId(), $results[0]->getId());
        $this->assertSame($inWindowLate->getId(), $results[1]->getId());

        // Out-of-window row and other-agent row must be excluded.
        $resultIds = array_map(static fn (LlmAgentCallLog $r): int => (int) $r->getId(), $results);
        $this->assertNotContains($outOfWindow->getId(), $resultIds);
        $this->assertNotContains($otherAgent->getId(), $resultIds);
    }

    public function testFindByAgentAndTimeRangeExcludesOutOfRange(): void
    {
        // Tight window covers only the last 30 minutes; seed one row just
        // before and one row well after — expect exactly one hit.
        $kept = $this->seed('flash_writer', new \DateTimeImmutable('-10 minutes'));
        $this->seed('flash_writer', new \DateTimeImmutable('-3 hours'));

        $results = $this->repository->findByAgentAndTimeRange(
            'flash_writer',
            new \DateTimeImmutable('-30 minutes'),
            new \DateTimeImmutable('+1 minute'),
        );

        $this->assertCount(1, $results);
        $this->assertSame($kept->getId(), $results[0]->getId());
    }

    public function testCountByVerdictAggregatesAllBuckets(): void
    {
        $now = new \DateTimeImmutable('-30 minutes');
        $this->seed('legal_guard', $now, verdict: 'pass');
        $this->seed('legal_guard', $now, verdict: 'pass');
        $this->seed('legal_guard', $now, verdict: 'fail');
        $this->seed('escalation_classifier', $now, verdict: 'escalate');
        $this->seed('flash_writer', $now, verdict: null); // writer leaves verdict null

        $counts = $this->repository->countByVerdict(
            new \DateTimeImmutable('-1 hour'),
            new \DateTimeImmutable('+1 minute'),
        );

        $this->assertSame(2, $counts['pass'] ?? null);
        $this->assertSame(1, $counts['fail'] ?? null);
        $this->assertSame(1, $counts['escalate'] ?? null);
        // NULL verdicts bucketed under the literal 'null' key.
        $this->assertSame(1, $counts['null'] ?? null);
    }

    public function testFindInTimeRangeReturnsAllAgentsOrderedAsc(): void
    {
        // Drives the CLI aggregation path — one row per agent in window,
        // ordered by created_at ASC so the table renders deterministically.
        $early = $this->seed('flash_writer', new \DateTimeImmutable('-90 minutes'));
        $mid = $this->seed('legal_guard', new \DateTimeImmutable('-60 minutes'));
        $late = $this->seed('escalation_classifier', new \DateTimeImmutable('-30 minutes'));

        $results = $this->repository->findInTimeRange(
            new \DateTimeImmutable('-2 hours'),
            new \DateTimeImmutable('+1 minute'),
        );

        // Filter to the rows WE seeded (other integration tests in the
        // suite may have left rows; assert on ids we own).
        $ours = array_values(array_filter(
            $results,
            fn (LlmAgentCallLog $r): bool => \in_array((int) $r->getId(), $this->idsToClean, true),
        ));

        $this->assertCount(3, $ours);
        $this->assertSame($early->getId(), $ours[0]->getId());
        $this->assertSame($mid->getId(), $ours[1]->getId());
        $this->assertSame($late->getId(), $ours[2]->getId());
    }

    private function seed(
        string $agent,
        \DateTimeImmutable $createdAt,
        ?string $verdict = null,
    ): LlmAgentCallLog {
        $row = new LlmAgentCallLog(
            agentName: $agent,
            invocationId: (string) new Ulid(),
            promptHash: str_repeat('c', 64),
            durationMs: 1000,
            inputTokenCount: 100,
            outputTokenCount: 50,
            verdict: $verdict,
            createdAt: $createdAt,
        );
        $this->em->persist($row);
        $this->em->flush();

        $id = $row->getId();
        $this->assertNotNull($id, 'seeded row must have an id after flush');
        $this->idsToClean[] = $id;

        return $row;
    }
}
