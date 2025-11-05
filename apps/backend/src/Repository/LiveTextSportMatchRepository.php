<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LiveTextSportMatch;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LiveTextSportMatch>
 */
class LiveTextSportMatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LiveTextSportMatch::class);
    }

    /**
     * Find all live sport matches (status = 'live').
     *
     * @return LiveTextSportMatch[]
     */
    public function findLiveMatches(): array
    {
        return $this->createQueryBuilder('sm')
            ->leftJoin('sm.liveText', 'lt')
            ->addSelect('lt')
            ->where('sm.status = :status')
            ->setParameter('status', 'live')
            ->orderBy('sm.scheduledStartTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find sport matches by sport type.
     *
     * @return LiveTextSportMatch[]
     */
    public function findBySportType(string $sportType): array
    {
        return $this->createQueryBuilder('sm')
            ->leftJoin('sm.liveText', 'lt')
            ->addSelect('lt')
            ->where('sm.sportType = :sportType')
            ->setParameter('sportType', $sportType)
            ->orderBy('sm.scheduledStartTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find sport matches by competition.
     *
     * @return LiveTextSportMatch[]
     */
    public function findByCompetition(string $competition): array
    {
        return $this->createQueryBuilder('sm')
            ->leftJoin('sm.liveText', 'lt')
            ->addSelect('lt')
            ->where('sm.competition = :competition')
            ->setParameter('competition', $competition)
            ->orderBy('sm.scheduledStartTime', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find upcoming matches (not started yet).
     *
     * @return LiveTextSportMatch[]
     */
    public function findUpcomingMatches(int $limit = 10): array
    {
        return $this->createQueryBuilder('sm')
            ->leftJoin('sm.liveText', 'lt')
            ->addSelect('lt')
            ->where('sm.status = :status')
            ->andWhere('sm.scheduledStartTime > :now')
            ->setParameter('status', 'not_started')
            ->setParameter('now', new DateTime())
            ->orderBy('sm.scheduledStartTime', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
