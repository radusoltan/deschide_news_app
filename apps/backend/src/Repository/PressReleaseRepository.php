<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PressRelease;
use App\Enum\PressReleaseStatus;
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
}
