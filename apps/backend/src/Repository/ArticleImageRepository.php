<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Article;
use App\Entity\ArticleImage;
use App\Entity\Image;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArticleImage>
 */
class ArticleImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArticleImage::class);
    }

    /**
     * Find featured image for article.
     */
    public function findFeaturedForArticle(Article $article): ?ArticleImage
    {
        return $this->createQueryBuilder('ai')
            ->where('ai.article = :article')
            ->andWhere('ai.isFeatured = true')
            ->setParameter('article', $article)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all images for article ordered by position.
     *
     * @return ArticleImage[]
     */
    public function findByArticleOrdered(Article $article): array
    {
        return $this->createQueryBuilder('ai')
            ->where('ai.article = :article')
            ->setParameter('article', $article)
            ->orderBy('ai.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find articles using an image.
     *
     * @return Article[]
     */
    public function findArticlesUsingImage(Image $image): array
    {
        return $this->createQueryBuilder('ai')
            ->select('a')
            ->join('ai.article', 'a')
            ->where('ai.image = :image')
            ->setParameter('image', $image)
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if image is already attached to article.
     */
    public function isImageAttachedToArticle(Article $article, Image $image): bool
    {
        $count = $this->createQueryBuilder('ai')
            ->select('COUNT(ai.id)')
            ->where('ai.article = :article')
            ->andWhere('ai.image = :image')
            ->setParameter('article', $article)
            ->setParameter('image', $image)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Find by article and image.
     */
    public function findOneByArticleAndImage(Article $article, Image $image): ?ArticleImage
    {
        return $this->createQueryBuilder('ai')
            ->where('ai.article = :article')
            ->andWhere('ai.image = :image')
            ->setParameter('article', $article)
            ->setParameter('image', $image)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
