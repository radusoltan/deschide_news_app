<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ShortLink;
use App\Entity\ShortLinkInteraction;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ShortLinkInteraction>
 *
 * @method ShortLinkInteraction|null find($id, $lockMode = null, $lockVersion = null)
 * @method ShortLinkInteraction|null findOneBy(array $criteria, array $orderBy = null)
 * @method ShortLinkInteraction[]    findAll()
 * @method ShortLinkInteraction[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ShortLinkInteractionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ShortLinkInteraction::class);
    }

    /**
     * Get clicks per day for a short link.
     *
     * @return array<array{date: string, clicks: int}>
     */
    public function getClicksPerDay(ShortLink $shortLink, int $days = 30): array
    {
        $fromDate = new DateTimeImmutable("-{$days} days");

        $qb = $this->createQueryBuilder('sli')
            ->select("DATE(sli.clickedAt) as date, COUNT(sli.id) as clicks")
            ->where('sli.shortLink = :shortLink')
            ->andWhere('sli.clickedAt >= :fromDate')
            ->setParameter('shortLink', $shortLink)
            ->setParameter('fromDate', $fromDate)
            ->groupBy('date')
            ->orderBy('date', 'ASC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Get top referrers for a short link.
     *
     * @return array<array{referrer: string, count: int}>
     */
    public function getTopReferrers(ShortLink $shortLink, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('sli')
            ->select("COALESCE(sli.referrer, 'Direct') as referrer, COUNT(sli.id) as count")
            ->where('sli.shortLink = :shortLink')
            ->setParameter('shortLink', $shortLink)
            ->groupBy('sli.referrer')
            ->orderBy('count', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Get device type distribution for a short link.
     *
     * @return array<array{deviceType: string, count: int}>
     */
    public function getDeviceTypeDistribution(ShortLink $shortLink): array
    {
        $qb = $this->createQueryBuilder('sli')
            ->select("COALESCE(sli.deviceType, 'unknown') as deviceType, COUNT(sli.id) as count")
            ->where('sli.shortLink = :shortLink')
            ->setParameter('shortLink', $shortLink)
            ->groupBy('sli.deviceType')
            ->orderBy('count', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Get country distribution for a short link.
     *
     * @return array<array{countryCode: string, count: int}>
     */
    public function getCountryDistribution(ShortLink $shortLink, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('sli')
            ->select("COALESCE(sli.countryCode, 'XX') as countryCode, COUNT(sli.id) as count")
            ->where('sli.shortLink = :shortLink')
            ->setParameter('shortLink', $shortLink)
            ->groupBy('sli.countryCode')
            ->orderBy('count', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Get total clicks for a short link within a date range.
     */
    public function getClickCount(ShortLink $shortLink, ?DateTimeImmutable $from = null, ?DateTimeImmutable $to = null): int
    {
        $qb = $this->createQueryBuilder('sli')
            ->select('COUNT(sli.id)')
            ->where('sli.shortLink = :shortLink')
            ->setParameter('shortLink', $shortLink);

        if ($from !== null) {
            $qb->andWhere('sli.clickedAt >= :from')
                ->setParameter('from', $from);
        }

        if ($to !== null) {
            $qb->andWhere('sli.clickedAt <= :to')
                ->setParameter('to', $to);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}
