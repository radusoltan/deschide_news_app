<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\VideoShow;
use App\Entity\YouTubeVideo;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<YouTubeVideo>
 *
 * @method YouTubeVideo|null find($id, $lockMode = null, $lockVersion = null)
 * @method YouTubeVideo|null findOneBy(array $criteria, array $orderBy = null)
 * @method YouTubeVideo[]    findAll()
 * @method YouTubeVideo[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class YouTubeVideoRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, YouTubeVideo::class);
    }

    /**
     * Find videos for homepage slider (not hidden, ordered by position/date)
     *
     * @return YouTubeVideo[]
     */
    public function findForHomepageSlider(int $limit = 12): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.isHidden = :hidden')
            ->setParameter('hidden', false)
            ->orderBy('v.isFeatured', 'DESC')
            ->addOrderBy('v.position', 'ASC')
            ->addOrderBy('v.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find videos by video show with pagination
     *
     * @return YouTubeVideo[]
     */
    public function findByVideoShow(VideoShow $videoShow, int $page = 1, int $limit = 12): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.videoShow = :show')
            ->andWhere('v.isHidden = :hidden')
            ->setParameter('show', $videoShow)
            ->setParameter('hidden', false)
            ->orderBy('v.publishedAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find video by YouTube ID
     */
    public function findByYoutubeId(string $youtubeId): ?YouTubeVideo
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.youtubeId = :youtubeId')
            ->setParameter('youtubeId', $youtubeId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find featured videos
     *
     * @return YouTubeVideo[]
     */
    public function findFeatured(int $limit = 6): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.isFeatured = :featured')
            ->andWhere('v.isHidden = :hidden')
            ->setParameter('featured', true)
            ->setParameter('hidden', false)
            ->orderBy('v.position', 'ASC')
            ->addOrderBy('v.publishedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count videos for a show
     */
    public function countByVideoShow(VideoShow $videoShow): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->andWhere('v.videoShow = :show')
            ->andWhere('v.isHidden = :hidden')
            ->setParameter('show', $videoShow)
            ->setParameter('hidden', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find videos that need stats update (not synced recently)
     *
     * @return YouTubeVideo[]
     */
    public function findNeedingStatsUpdate(\DateTimeImmutable $olderThan, int $limit = 50): array
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.syncedAt IS NULL OR v.syncedAt < :olderThan')
            ->setParameter('olderThan', $olderThan)
            ->orderBy('v.syncedAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all YouTube IDs (for sync checking)
     *
     * @return string[]
     */
    public function getAllYoutubeIds(): array
    {
        $result = $this->createQueryBuilder('v')
            ->select('v.youtubeId')
            ->getQuery()
            ->getScalarResult();

        return array_column($result, 'youtubeId');
    }
}
