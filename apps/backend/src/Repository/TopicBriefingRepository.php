<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Topic;
use App\Entity\TopicBriefing;
use App\Enum\BriefingCadence;
use App\Enum\BriefingStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TopicBriefing>
 */
class TopicBriefingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TopicBriefing::class);
    }

    /**
     * Find the latest briefing for a topic and cadence.
     */
    public function findLatestForTopic(Topic $topic, BriefingCadence $cadence): ?TopicBriefing
    {
        return $this->createQueryBuilder('tb')
            ->where('tb.topic = :topic')
            ->andWhere('tb.cadence = :cadence')
            ->setParameter('topic', $topic)
            ->setParameter('cadence', $cadence)
            ->orderBy('tb.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find all briefings for a cadence with a given status.
     *
     * @return TopicBriefing[]
     */
    public function findByCadenceAndStatus(BriefingCadence $cadence, BriefingStatus $status, int $limit = 50): array
    {
        return $this->createQueryBuilder('tb')
            ->leftJoin('tb.topic', 't')
            ->addSelect('t')
            ->where('tb.cadence = :cadence')
            ->andWhere('tb.status = :status')
            ->setParameter('cadence', $cadence)
            ->setParameter('status', $status)
            ->orderBy('tb.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent briefings for a cadence (for admin panel display).
     *
     * @return TopicBriefing[]
     */
    public function findRecentByCadence(BriefingCadence $cadence, int $limit = 20): array
    {
        return $this->createQueryBuilder('tb')
            ->leftJoin('tb.topic', 't')
            ->addSelect('t')
            ->where('tb.cadence = :cadence')
            ->setParameter('cadence', $cadence)
            ->orderBy('tb.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
