<?php

declare(strict_types=1);

namespace App\Repository\Editorial;

use App\Entity\Editorial\SourceClaimHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SourceClaimHistory>
 *
 * Sprint 53 schema-only — business-logic queries arrive in Sprint 54.
 */
class SourceClaimHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SourceClaimHistory::class);
    }
}
