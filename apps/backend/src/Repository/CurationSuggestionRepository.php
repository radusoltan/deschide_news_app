<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\CurationSuggestion;
use App\Enum\CurationSuggestionStatus;
use App\Enum\CurationSuggestionType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CurationSuggestion>
 */
class CurationSuggestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CurationSuggestion::class);
    }

    public function countPending(): int
    {
        return $this->count(['status' => CurationSuggestionStatus::PENDING]);
    }

    /**
     * Check if a suggestion with the same type and cluster IDs was created recently.
     */
    /**
     * @param list<int> $clusterIds
     */
    public function existsRecentForClusters(CurationSuggestionType $type, array $clusterIds, int $withinDays = 7): bool
    {
        sort($clusterIds);
        $since = new \DateTimeImmutable("-{$withinDays} days");

        $suggestions = $this->createQueryBuilder('cs')
            ->where('cs.type = :type')
            ->andWhere('cs.suggestedAt >= :since')
            ->setParameter('type', $type)
            ->setParameter('since', $since)
            ->getQuery()
            ->getResult();

        foreach ($suggestions as $suggestion) {
            $ids = $suggestion->getClusterIds();
            sort($ids);
            if ($ids === $clusterIds) {
                return true;
            }
        }

        return false;
    }
}
