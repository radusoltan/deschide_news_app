<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RelevanceKeyword;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RelevanceKeyword>
 */
class RelevanceKeywordRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RelevanceKeyword::class);
    }

    /**
     * @return RelevanceKeyword[]
     */
    public function findAllActive(): array
    {
        return $this->findBy(['isActive' => true], ['tier' => 'ASC', 'keyword' => 'ASC']);
    }

    /**
     * @return RelevanceKeyword[]
     */
    public function findByTier(int $tier): array
    {
        return $this->findBy(['tier' => $tier, 'isActive' => true]);
    }
}
