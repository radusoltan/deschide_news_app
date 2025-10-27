<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Image;
use App\Entity\Thumbnail;
use App\Entity\ThumbnailProfile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Thumbnail>
 */
class ThumbnailRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Thumbnail::class);
    }

    /**
     * Find all thumbnails for an image
     *
     * @return Thumbnail[]
     */
    public function findByImage(Image $image): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.image = :image')
            ->setParameter('image', $image)
            ->orderBy('t.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find thumbnail for image and profile
     */
    public function findOneByImageAndProfile(Image $image, ThumbnailProfile $profile): ?Thumbnail
    {
        return $this->createQueryBuilder('t')
            ->where('t.image = :image')
            ->andWhere('t.profile = :profile')
            ->setParameter('image', $image)
            ->setParameter('profile', $profile)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all thumbnails for a profile
     *
     * @return Thumbnail[]
     */
    public function findByProfile(ThumbnailProfile $profile): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.profile = :profile')
            ->setParameter('profile', $profile)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Check if thumbnail exists for image and profile
     */
    public function existsForImageAndProfile(Image $image, ThumbnailProfile $profile): bool
    {
        $count = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.image = :image')
            ->andWhere('t.profile = :profile')
            ->setParameter('image', $image)
            ->setParameter('profile', $profile)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Delete all thumbnails for an image
     */
    public function deleteByImage(Image $image): int
    {
        return $this->createQueryBuilder('t')
            ->delete()
            ->where('t.image = :image')
            ->setParameter('image', $image)
            ->getQuery()
            ->execute();
    }

    /**
     * Delete all thumbnails for a profile
     */
    public function deleteByProfile(ThumbnailProfile $profile): int
    {
        return $this->createQueryBuilder('t')
            ->delete()
            ->where('t.profile = :profile')
            ->setParameter('profile', $profile)
            ->getQuery()
            ->execute();
    }
}
