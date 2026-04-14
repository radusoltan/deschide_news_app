<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PressRelease;
use App\Enum\PressReleaseStatus;
use App\Enum\SourceType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PressRelease>
 */
class PressReleaseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PressRelease::class);
    }

    public function countPending(): int
    {
        return $this->count(['status' => PressReleaseStatus::PENDING]);
    }

    public function findBySourceEmailId(string $sourceEmailId): ?PressRelease
    {
        return $this->findOneBy(['sourceEmailId' => $sourceEmailId]);
    }

    public function findByContentHash(string $hash): ?PressRelease
    {
        return $this->findOneBy(['contentHash' => $hash]);
    }

    public function findByContentHashAndSourceType(string $hash, SourceType $type): ?PressRelease
    {
        return $this->findOneBy(['contentHash' => $hash, 'sourceType' => $type]);
    }

    public function findBySourceUrl(string $sourceUrl): ?PressRelease
    {
        return $this->findOneBy(['sourceUrl' => $sourceUrl]);
    }

    public function countBySourceType(SourceType $type): int
    {
        return $this->count(['sourceType' => $type]);
    }

    /**
     * @return array<string, int> Pending count per source type, e.g. ['email' => 8, 'scrape' => 15]
     */
    /**
     * Find a PressRelease with the same content_hash that is already assigned to a cluster.
     */
    public function findClusteredDuplicateByHash(string $hash, int $excludeId): ?PressRelease
    {
        return $this->createQueryBuilder('pr')
            ->innerJoin('pr.storyClusters', 'sc')
            ->where('pr.contentHash = :hash')
            ->andWhere('pr.id != :excludeId')
            ->setParameter('hash', $hash)
            ->setParameter('excludeId', $excludeId)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countPendingBySourceType(): array
    {
        $rows = $this->createQueryBuilder('pr')
            ->select('pr.sourceType AS type, COUNT(pr.id) AS cnt')
            ->where('pr.status = :status')
            ->setParameter('status', PressReleaseStatus::PENDING)
            ->groupBy('pr.sourceType')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[$row['type']->value ?? $row['type']] = (int) $row['cnt'];
        }

        return $result;
    }
}
