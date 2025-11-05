<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LiveTextCollaborator;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LiveTextCollaborator>
 */
class LiveTextCollaboratorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LiveTextCollaborator::class);
    }
}
