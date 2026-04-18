<?php

declare(strict_types=1);

namespace App\Repository\Editorial;

use App\Entity\Editorial\EditorialEscalationLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EditorialEscalationLog>
 *
 * Sprint 53 schema-only — business-logic queries arrive in Sprint 55.
 */
class EditorialEscalationLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EditorialEscalationLog::class);
    }
}
