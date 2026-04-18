<?php

declare(strict_types=1);

namespace App\Repository\Editorial;

use App\Entity\Editorial\VerifiedSource;
use App\Enum\EditorialAlignment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VerifiedSource>
 */
class VerifiedSourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VerifiedSource::class);
    }

    public function findBySlug(string $slug): ?VerifiedSource
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    /**
     * @return list<VerifiedSource>
     */
    public function findEnabledByAlignment(EditorialAlignment $alignment): array
    {
        /** @var list<VerifiedSource> $rows */
        $rows = $this->createQueryBuilder('vs')
            ->andWhere('vs.editorialAlignment = :alignment')
            ->andWhere('vs.enabled = :enabled')
            ->setParameter('alignment', $alignment)
            ->setParameter('enabled', true)
            ->orderBy('vs.tier', 'ASC')
            ->addOrderBy('vs.slug', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * @param list<EditorialAlignment> $alignments
     *
     * @return list<VerifiedSource>
     */
    public function findEnabledByAlignments(array $alignments): array
    {
        if ($alignments === []) {
            return [];
        }

        /** @var list<VerifiedSource> $rows */
        $rows = $this->createQueryBuilder('vs')
            ->andWhere('vs.editorialAlignment IN (:alignments)')
            ->andWhere('vs.enabled = :enabled')
            ->setParameter('alignments', $alignments)
            ->setParameter('enabled', true)
            ->orderBy('vs.tier', 'ASC')
            ->addOrderBy('vs.slug', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }

    /**
     * @return list<VerifiedSource>
     */
    public function findEnabledByTier(int $tier): array
    {
        /** @var list<VerifiedSource> $rows */
        $rows = $this->createQueryBuilder('vs')
            ->andWhere('vs.tier = :tier')
            ->andWhere('vs.enabled = :enabled')
            ->setParameter('tier', $tier)
            ->setParameter('enabled', true)
            ->orderBy('vs.slug', 'ASC')
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
