<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ShortLink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShortLink>
 *
 * @method ShortLink|null find($id, $lockMode = null, $lockVersion = null)
 * @method ShortLink|null findOneBy(array $criteria, array $orderBy = null)
 * @method ShortLink[]    findAll()
 * @method ShortLink[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ShortLinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShortLink::class);
    }

    public function findByCode(string $code): ?ShortLink
    {
        return $this->findOneBy(['code' => $code]);
    }

    public function codeExists(string $code): bool
    {
        return $this->count(['code' => $code]) > 0;
    }

    /**
     * Get short links with most clicks.
     *
     * @return ShortLink[]
     */
    public function findTopLinks(int $limit = 10): array
    {
        return $this->createQueryBuilder('sl')
            ->orderBy('sl.clickCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get recently created short links.
     *
     * @return ShortLink[]
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('sl')
            ->orderBy('sl.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find short link by article.
     */
    public function findByArticle(int $articleId): ?ShortLink
    {
        return $this->findOneBy(['article' => $articleId]);
    }
}
