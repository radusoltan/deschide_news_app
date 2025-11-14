<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Author;
use App\Enum\AuthorStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Author>
 */
class AuthorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Author::class);
    }

    /**
     * Find active authors.
     *
     * @return Author[]
     */
    public function findActiveAuthors(): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.isActive = true')
            ->andWhere('a.status = :status')
            ->setParameter('status', AuthorStatus::ACTIVE)
            ->orderBy('a.lastName', 'ASC')
            ->addOrderBy('a.firstName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find authors with published articles.
     *
     * @return array<int, array{author: Author, articleCount: int}>
     */
    public function findAuthorsWithArticles(): array
    {
        return $this->createQueryBuilder('a')
            ->select('a', 'COUNT(art.id) as articleCount')
            ->leftJoin('a.articles', 'art', 'WITH', 'art.status = :published')
            ->where('a.isActive = true')
            ->setParameter('published', 'published')
            ->groupBy('a.id')
            ->having('COUNT(art.id) > 0')
            ->orderBy('articleCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find author by slug.
     */
    public function findOneBySlug(string $slug): ?Author
    {
        return $this->createQueryBuilder('a')
            ->where('a.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find top authors (most articles).
     *
     * @return Author[]
     */
    public function findTopAuthors(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->select('a', 'COUNT(art.id) as HIDDEN articleCount')
            ->leftJoin('a.articles', 'art', 'WITH', 'art.status = :published')
            ->where('a.isActive = true')
            ->setParameter('published', 'published')
            ->groupBy('a.id')
            ->orderBy('articleCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
