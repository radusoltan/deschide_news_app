<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LiveTextMatchEvent;
use App\Entity\LiveTextSportMatch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LiveTextMatchEvent>
 */
class LiveTextMatchEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LiveTextMatchEvent::class);
    }

    /**
     * Find all events for a sport match.
     *
     * @return LiveTextMatchEvent[]
     */
    public function findByMatch(LiveTextSportMatch $sportMatch): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.sportMatch = :match')
            ->setParameter('match', $sportMatch)
            ->orderBy('e.eventMinute', 'ASC')
            ->addOrderBy('e.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find events by type.
     *
     * @return LiveTextMatchEvent[]
     */
    public function findByType(LiveTextSportMatch $sportMatch, string $eventType): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.sportMatch = :match')
            ->andWhere('e.eventType = :type')
            ->setParameter('match', $sportMatch)
            ->setParameter('type', $eventType)
            ->orderBy('e.eventMinute', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get goal count by team.
     *
     * @param string $team (home or away)
     */
    public function getGoalCountByTeam(LiveTextSportMatch $sportMatch, string $team): int
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.sportMatch = :match')
            ->andWhere('e.team = :team')
            ->andWhere('e.eventType IN (:goalTypes)')
            ->setParameter('match', $sportMatch)
            ->setParameter('team', $team)
            ->setParameter('goalTypes', ['goal', 'penalty_goal']);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Get card count by team.
     *
     * @param string $cardType ('yellow_card' or 'red_card')
     */
    public function getCardCountByTeam(LiveTextSportMatch $sportMatch, string $team, string $cardType): int
    {
        $qb = $this->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.sportMatch = :match')
            ->andWhere('e.team = :team')
            ->andWhere('e.eventType = :cardType')
            ->setParameter('match', $sportMatch)
            ->setParameter('team', $team)
            ->setParameter('cardType', $cardType);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Get latest events for a match.
     *
     * @return LiveTextMatchEvent[]
     */
    public function findLatestEvents(LiveTextSportMatch $sportMatch, int $limit = 5): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.sportMatch = :match')
            ->setParameter('match', $sportMatch)
            ->orderBy('e.eventMinute', 'DESC')
            ->addOrderBy('e.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
