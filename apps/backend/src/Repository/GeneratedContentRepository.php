<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\GeneratedContent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<GeneratedContent>
 */
class GeneratedContentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GeneratedContent::class);
    }

    /**
     * @return list<GeneratedContent>
     */
    public function findLatestByType(string $type, int $limit = 1): array
    {
        return $this->createQueryBuilder('gc')
            ->where('gc.type = :type')
            ->setParameter('type', $type)
            ->orderBy('gc.generatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<GeneratedContent>
     */
    public function findByDateRange(string $type, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        return $this->createQueryBuilder('gc')
            ->where('gc.type = :type')
            ->andWhere('gc.generatedAt >= :from')
            ->andWhere('gc.generatedAt <= :to')
            ->setParameter('type', $type)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('gc.generatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLatestBriefing(string $locale = 'ro'): ?GeneratedContent
    {
        return $this->createQueryBuilder('gc')
            ->where('gc.type = :type')
            ->andWhere('gc.locale = :locale')
            ->setParameter('type', 'daily_briefing')
            ->setParameter('locale', $locale)
            ->orderBy('gc.generatedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
