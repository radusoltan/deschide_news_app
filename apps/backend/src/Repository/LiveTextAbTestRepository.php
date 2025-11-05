<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LiveTextAbTest;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LiveTextAbTest>
 */
class LiveTextAbTestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LiveTextAbTest::class);
    }

    /**
     * Find running tests.
     */
    public function findRunningTests(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->andWhere('t.startDate <= :now')
            ->andWhere('t.endDate >= :now OR t.endDate IS NULL')
            ->setParameter('status', 'running')
            ->setParameter('now', new DateTime())
            ->getQuery()
            ->getResult();
    }

    /**
     * Find tests by status.
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->setParameter('status', $status)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
