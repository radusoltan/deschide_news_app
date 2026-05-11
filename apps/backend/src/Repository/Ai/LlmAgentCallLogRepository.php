<?php

declare(strict_types=1);

namespace App\Repository\Ai;

use App\Entity\Ai\LlmAgentCallLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LlmAgentCallLog>
 */
class LlmAgentCallLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LlmAgentCallLog::class);
    }

    /**
     * @return list<LlmAgentCallLog>
     */
    public function findByAgentAndTimeRange(
        string $agent,
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): array {
        /** @var list<LlmAgentCallLog> $result */
        $result = $this->createQueryBuilder('l')
            ->andWhere('l.agentName = :agent')
            ->andWhere('l.createdAt >= :from')
            ->andWhere('l.createdAt <= :to')
            ->setParameter('agent', $agent)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('l.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Count invocations grouped by verdict within a time window. NULL
     * verdicts (writers, non-verdict agents) are bucketed under the literal
     * key `'null'` so JSON consumers never encounter a null map key.
     *
     * @return array<string, int>
     */
    public function countByVerdict(
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): array {
        /** @var list<array{verdict: ?string, cnt: int}> $rows */
        $rows = $this->createQueryBuilder('l')
            ->select('l.verdict AS verdict', 'COUNT(l.id) AS cnt')
            ->andWhere('l.createdAt >= :from')
            ->andWhere('l.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->groupBy('l.verdict')
            ->getQuery()
            ->getArrayResult();

        $out = [];
        foreach ($rows as $row) {
            $key = $row['verdict'] ?? 'null';
            $out[$key] = (int) $row['cnt'];
        }

        return $out;
    }

    /**
     * Fetch every row in the given window ordered by time ASC. Used by the
     * `app:editorial:llm-cost-summary` command to rebuild the S55 aggregate
     * from the DB (the grep path is retired in T56.09).
     *
     * @return list<LlmAgentCallLog>
     */
    public function findInTimeRange(
        \DateTimeInterface $from,
        \DateTimeInterface $to,
    ): array {
        /** @var list<LlmAgentCallLog> $result */
        $result = $this->createQueryBuilder('l')
            ->andWhere('l.createdAt >= :from')
            ->andWhere('l.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('l.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }
}
