<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\VideoShow;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VideoShow>
 *
 * @method VideoShow|null find($id, $lockMode = null, $lockVersion = null)
 * @method VideoShow|null findOneBy(array $criteria, array $orderBy = null)
 * @method VideoShow[]    findAll()
 * @method VideoShow[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class VideoShowRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VideoShow::class);
    }

    /**
     * Find all active video shows ordered by position
     *
     * @return VideoShow[]
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('vs')
            ->andWhere('vs.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('vs.position', 'ASC')
            ->addOrderBy('vs.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find video show by slug
     */
    public function findBySlug(string $slug): ?VideoShow
    {
        return $this->createQueryBuilder('vs')
            ->andWhere('vs.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find video shows with video count
     *
     * @return array<array{show: VideoShow, videoCount: int}>
     */
    public function findWithVideoCount(): array
    {
        return $this->createQueryBuilder('vs')
            ->select('vs', 'COUNT(v.id) as videoCount')
            ->leftJoin('vs.videos', 'v')
            ->andWhere('vs.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('vs.id')
            ->orderBy('vs.position', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
