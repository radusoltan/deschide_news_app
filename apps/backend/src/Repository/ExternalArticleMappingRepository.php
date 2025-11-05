<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Article;
use App\Entity\ExternalArticleMapping;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ExternalArticleMapping>
 */
class ExternalArticleMappingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ExternalArticleMapping::class);
    }

    /**
     * Find article by external ID and source.
     */
    public function findArticleByExternalId(string $source, string $externalId): ?Article
    {
        $mapping = $this->findOneBy([
            'source' => $source,
            'externalId' => $externalId,
        ]);

        return $mapping?->getArticle();
    }

    /**
     * Check if external ID exists for a source.
     */
    public function existsByExternalId(string $source, string $externalId): bool
    {
        return $this->count([
            'source' => $source,
            'externalId' => $externalId,
        ]) > 0;
    }

    /**
     * Get all mappings for a specific source.
     */
    public function findBySource(string $source): array
    {
        return $this->findBy(['source' => $source], ['createdAt' => 'DESC']);
    }

    /**
     * Get statistics by source.
     */
    public function getStatsBySource(): array
    {
        $qb = $this->createQueryBuilder('m');

        return $qb->select('m.source, COUNT(m.id) as total')
            ->groupBy('m.source')
            ->getQuery()
            ->getResult();
    }
}
