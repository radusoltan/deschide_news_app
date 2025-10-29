<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Article;
use App\Entity\ArticleLock;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArticleLock>
 */
class ArticleLockRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArticleLock::class);
    }

    /**
     * Find active (non-expired) lock for an article.
     */
    public function findActiveLockForArticle(Article $article): ?ArticleLock
    {
        return $this->createQueryBuilder('al')
            ->where('al.article = :article')
            ->andWhere('al.expiresAt > :now')
            ->setParameter('article', $article)
            ->setParameter('now', new \DateTimeImmutable())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find lock for article by specific user.
     */
    public function findLockForArticleAndUser(Article $article, User $user): ?ArticleLock
    {
        return $this->createQueryBuilder('al')
            ->where('al.article = :article')
            ->andWhere('al.lockedBy = :user')
            ->andWhere('al.expiresAt > :now')
            ->setParameter('article', $article)
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Delete expired locks.
     */
    public function deleteExpiredLocks(): int
    {
        return $this->createQueryBuilder('al')
            ->delete()
            ->where('al.expiresAt <= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    /**
     * Find all active locks for a user.
     */
    public function findActiveLocksForUser(User $user): array
    {
        return $this->createQueryBuilder('al')
            ->where('al.lockedBy = :user')
            ->andWhere('al.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * Release lock for article.
     */
    public function releaseLock(Article $article, User $user): bool
    {
        $deleted = $this->createQueryBuilder('al')
            ->delete()
            ->where('al.article = :article')
            ->andWhere('al.lockedBy = :user')
            ->setParameter('article', $article)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();

        return $deleted > 0;
    }
}
