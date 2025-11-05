<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LiveTextPost;
use App\Entity\LiveTextReaction;
use App\Enum\ReactionType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LiveTextReaction>
 */
class LiveTextReactionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LiveTextReaction::class);
    }

    /**
     * Get reaction counts grouped by type for a specific post.
     *
     * @return array<string, int> Array with reaction type as key and count as value
     */
    public function getReactionCountsByPost(LiveTextPost $post): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select('r.reactionType as type', 'COUNT(r.id) as count')
            ->where('r.liveTextPost = :post')
            ->setParameter('post', $post)
            ->groupBy('r.reactionType');

        $results = $qb->getQuery()->getResult();

        // Initialize all reaction types with 0
        $counts = [];
        foreach (ReactionType::cases() as $reactionType) {
            $counts[$reactionType->value] = 0;
        }

        // Fill in actual counts
        foreach ($results as $result) {
            $counts[$result['type']->value] = (int) $result['count'];
        }

        return $counts;
    }

    /**
     * Check if a user has already reacted to a post.
     */
    public function hasUserReacted(LiveTextPost $post, ?int $userId, ?string $ipAddress): bool
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.liveTextPost = :post')
            ->setParameter('post', $post);

        if ($userId) {
            $qb->andWhere('r.user = :userId')
                ->setParameter('userId', $userId);
        } else {
            $qb->andWhere('r.ipAddress = :ipAddress')
                ->setParameter('ipAddress', $ipAddress);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Get user's reaction for a post.
     */
    public function getUserReaction(LiveTextPost $post, ?int $userId, ?string $ipAddress): ?LiveTextReaction
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.liveTextPost = :post')
            ->setParameter('post', $post);

        if ($userId) {
            $qb->andWhere('r.user = :userId')
                ->setParameter('userId', $userId);
        } else {
            $qb->andWhere('r.ipAddress = :ipAddress')
                ->setParameter('ipAddress', $ipAddress);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Remove user's reaction for a post.
     */
    public function removeUserReaction(LiveTextPost $post, ?int $userId, ?string $ipAddress): bool
    {
        $reaction = $this->getUserReaction($post, $userId, $ipAddress);

        if ($reaction) {
            $this->getEntityManager()->remove($reaction);
            $this->getEntityManager()->flush();

            return true;
        }

        return false;
    }
}
