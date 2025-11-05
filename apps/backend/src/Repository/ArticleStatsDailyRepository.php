<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ArticleStatsDaily;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ArticleStatsDaily>
 */
class ArticleStatsDailyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ArticleStatsDaily::class);
    }

    /**
     * Find stats by article and date range.
     *
     * @return ArticleStatsDaily[]
     */
    public function findByArticleAndDateRange(int $articleId, DateTime $start, DateTime $end): array
    {
        return $this->createQueryBuilder('asd')
            ->andWhere('asd.article = :articleId')
            ->andWhere('asd.date BETWEEN :start AND :end')
            ->setParameter('articleId', $articleId)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('asd.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find top articles by views for a specific date.
     *
     * @return ArticleStatsDaily[]
     */
    public function findTopArticlesByViews(int $limit, DateTime $date): array
    {
        return $this->createQueryBuilder('asd')
            ->andWhere('asd.date = :date')
            ->setParameter('date', $date)
            ->orderBy('asd.views', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find or create stats entry for article and date.
     */
    public function findOneByArticleAndDate(int $articleId, DateTime $date): ?ArticleStatsDaily
    {
        return $this->createQueryBuilder('asd')
            ->andWhere('asd.article = :articleId')
            ->andWhere('asd.date = :date')
            ->setParameter('articleId', $articleId)
            ->setParameter('date', $date)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get total views for an article across all time.
     */
    public function getTotalViewsByArticle(int $articleId): int
    {
        $result = $this->createQueryBuilder('asd')
            ->select('SUM(asd.views)')
            ->andWhere('asd.article = :articleId')
            ->setParameter('articleId', $articleId)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Get trending articles (most views in date range).
     *
     * @return array<array{article_id: int, total_views: int}>
     */
    public function getTrendingArticles(int $limit, DateTime $start, DateTime $end): array
    {
        return $this->createQueryBuilder('asd')
            ->select('IDENTITY(asd.article) as article_id, SUM(asd.views) as total_views')
            ->andWhere('asd.date BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->groupBy('asd.article')
            ->orderBy('total_views', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
