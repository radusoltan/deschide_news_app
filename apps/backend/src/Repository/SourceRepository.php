<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Source;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Source>
 */
class SourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Source::class);
    }

    public function findByName(string $name): ?Source
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * Find a source that matches a given domain (e.g., "reuters.com").
     */
    public function findByDomain(string $domain): ?Source
    {
        return $this->createQueryBuilder('s')
            ->where('s.domainPattern = :domain')
            ->orWhere('LOWER(s.name) LIKE :domainLike')
            ->setParameter('domain', $domain)
            ->setParameter('domainLike', '%' . strtolower($domain) . '%')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Source>
     */
    public function findActive(): array
    {
        return $this->findBy(['isActive' => true], ['credibilityWeight' => 'DESC']);
    }

    /**
     * Get credibility weight for a source name or domain.
     * Falls back to 0.5 if not found.
     */
    public function getCredibilityWeight(string $sourceNameOrDomain): float
    {
        $source = $this->findByName($sourceNameOrDomain)
            ?? $this->findByDomain($sourceNameOrDomain);

        return $source?->getCredibilityWeight() ?? 0.5;
    }
}
