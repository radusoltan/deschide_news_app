<?php

declare(strict_types=1);

namespace App\Repository\Editorial;

use App\Entity\Editorial\SourceSignal;
use App\Entity\Editorial\VerifiedSource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SourceSignal>
 */
class SourceSignalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SourceSignal::class);
    }

    /**
     * @return list<SourceSignal>
     */
    public function findRecentBySource(VerifiedSource $source, int $hours): array
    {
        $since = new \DateTimeImmutable(sprintf('-%d hours', $hours));

        /** @var list<SourceSignal> $rows */
        $rows = $this->createQueryBuilder('s')
            ->andWhere('s.verifiedSource = :source')
            ->andWhere('s.capturedAt >= :since')
            ->setParameter('source', $source)
            ->setParameter('since', $since)
            ->orderBy('s.capturedAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    public function existsByHash(VerifiedSource $source, string $hash): bool
    {
        $count = (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.verifiedSource = :source')
            ->andWhere('s.rawContentHash = :hash')
            ->setParameter('source', $source)
            ->setParameter('hash', $hash)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
