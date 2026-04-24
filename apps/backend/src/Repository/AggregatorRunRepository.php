<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AggregatorRun;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AggregatorRun>
 */
class AggregatorRunRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AggregatorRun::class);
    }

    /**
     * Find the latest run for a given source.
     */
    public function findLatestBySource(string $source): ?AggregatorRun
    {
        return $this->createQueryBuilder('r')
            ->where('r.source = :source')
            ->setParameter('source', $source)
            ->orderBy('r.startedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find runs with a given status.
     *
     * @return AggregatorRun[]
     */
    public function findByStatus(string $status, int $limit = 20): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.status = :status')
            ->setParameter('status', $status)
            ->orderBy('r.startedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
