<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ThumbnailProfile;
use App\Enum\ThumbnailCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ThumbnailProfile>
 */
class ThumbnailProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ThumbnailProfile::class);
    }

    /**
     * Find active profiles by category
     *
     * @param ThumbnailCategory[] $categories
     * @return ThumbnailProfile[]
     */
    public function findActiveByCategories(array $categories): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isActive = true')
            ->andWhere('p.category IN (:categories)')
            ->setParameter('categories', $categories)
            ->orderBy('p.width', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active profiles for articles
     *
     * @return ThumbnailProfile[]
     */
    public function findActiveForArticles(): array
    {
        return $this->findActiveByCategories([
            ThumbnailCategory::ARTICLE,
            ThumbnailCategory::GENERAL
        ]);
    }

    /**
     * Find active profiles for author profiles
     *
     * @return ThumbnailProfile[]
     */
    public function findActiveForProfiles(): array
    {
        return $this->findActiveByCategories([
            ThumbnailCategory::PROFILE,
            ThumbnailCategory::GENERAL
        ]);
    }

    /**
     * Find profile by name
     */
    public function findOneByName(string $name): ?ThumbnailProfile
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * Find all active profiles
     *
     * @return ThumbnailProfile[]
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.isActive = true')
            ->orderBy('p.category', 'ASC')
            ->addOrderBy('p.width', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if profile exists by dimensions and mode
     */
    public function existsByDimensionsAndMode(int $width, int $height, string $mode, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.width = :width')
            ->andWhere('p.height = :height')
            ->andWhere('p.mode = :mode')
            ->setParameter('width', $width)
            ->setParameter('height', $height)
            ->setParameter('mode', $mode);

        if ($excludeId !== null) {
            $qb->andWhere('p.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        $count = $qb->getQuery()->getSingleScalarResult();

        return $count > 0;
    }
}
