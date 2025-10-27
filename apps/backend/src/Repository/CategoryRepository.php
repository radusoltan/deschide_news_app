<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Category;
use App\Enum\CategoryStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * Find active categories for front page
     *
     * @return Category[]
     */
    public function findFrontPageCategories(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->andWhere('c.onFrontPage = true')
            ->setParameter('status', CategoryStatus::ACTIVE)
            ->orderBy('c.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all active categories
     *
     * @return Category[]
     */
    public function findActiveCategories(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.status = :status')
            ->setParameter('status', CategoryStatus::ACTIVE)
            ->orderBy('c.title', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find categories with article count
     *
     * @return array<int, array{category: Category, articleCount: int}>
     */
    public function findCategoriesWithArticleCount(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c', 'COUNT(a.id) as articleCount')
            ->leftJoin('c.articles', 'a', 'WITH', 'a.status = :published')
            ->where('c.status = :active')
            ->setParameter('published', 'published')
            ->setParameter('active', CategoryStatus::ACTIVE)
            ->groupBy('c.id')
            ->orderBy('articleCount', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
