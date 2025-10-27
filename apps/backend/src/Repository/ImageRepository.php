<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Image;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Image>
 */
class ImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Image::class);
    }

    /**
     * Find image by filename
     */
    public function findOneByFilename(string $filename): ?Image
    {
        return $this->createQueryBuilder('i')
            ->where('i.filename = :filename')
            ->setParameter('filename', $filename)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find recent images
     *
     * @return Image[]
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('i')
            ->orderBy('i.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find images by MIME type
     *
     * @return Image[]
     */
    public function findByMimeType(string $mimeType): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.mimeType = :mimeType')
            ->setParameter('mimeType', $mimeType)
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find large images (for optimization)
     *
     * @return Image[]
     */
    public function findLargeImages(int $sizeThreshold = 5242880): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.size > :threshold')
            ->setParameter('threshold', $sizeThreshold)
            ->orderBy('i.size', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
