<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LiveTextTemplate;
use App\Enum\TemplateType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LiveTextTemplate>
 */
class LiveTextTemplateRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LiveTextTemplate::class);
    }

    /**
     * Find templates by type.
     *
     * @return LiveTextTemplate[]
     */
    public function findByType(TemplateType $type): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.type = :type')
            ->setParameter('type', $type)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find system-provided templates.
     *
     * @return LiveTextTemplate[]
     */
    public function findSystemTemplates(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.isSystem = :isSystem')
            ->setParameter('isSystem', true)
            ->orderBy('t.type', 'ASC')
            ->addOrderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find custom (user-created) templates.
     *
     * @return LiveTextTemplate[]
     */
    public function findCustomTemplates(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.isSystem = :isSystem')
            ->setParameter('isSystem', false)
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
