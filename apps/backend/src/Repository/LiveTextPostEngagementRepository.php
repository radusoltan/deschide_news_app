<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LiveText;
use App\Entity\LiveTextPostEngagement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LiveTextPostEngagement>
 */
class LiveTextPostEngagementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LiveTextPostEngagement::class);
    }

    /**
     * Get heatmap data for LiveText
     * Returns engagement count per post.
     */
    public function getHeatmapData(LiveText $liveText): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = '
            SELECT
                p.id as post_id,
                COUNT(DISTINCT e.session_id) as unique_engagements,
                COUNT(e.id) as total_engagements,
                AVG(e.time_spent) as avg_time_spent,
                AVG(e.scroll_depth) as avg_scroll_depth
            FROM live_text_posts p
            LEFT JOIN live_text_post_engagements e ON e.post_id = p.id
            WHERE p.live_text_id = :liveTextId
            GROUP BY p.id
            ORDER BY p.published_at DESC
        ';

        $result = $conn->executeQuery($sql, ['liveTextId' => $liveText->getId()]);

        return $result->fetchAllAssociative();
    }

    /**
     * Get engagement funnel for LiveText
     * Returns count of users at each engagement stage.
     */
    public function getEngagementFunnel(LiveText $liveText): array
    {
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT
                e.engagement_type,
                COUNT(DISTINCT e.session_id) as unique_users,
                COUNT(e.id) as total_events
            FROM live_text_post_engagements e
            JOIN live_text_posts p ON p.id = e.post_id
            WHERE p.live_text_id = :liveTextId
            GROUP BY e.engagement_type
            ORDER BY
                CASE e.engagement_type
                    WHEN 'view' THEN 1
                    WHEN 'read' THEN 2
                    WHEN 'click' THEN 3
                    WHEN 'reaction' THEN 4
                    WHEN 'share' THEN 5
                    ELSE 6
                END
        ";

        $result = $conn->executeQuery($sql, ['liveTextId' => $liveText->getId()]);

        return $result->fetchAllAssociative();
    }

    /**
     * Get top engaged posts.
     */
    public function getTopEngagedPosts(LiveText $liveText, int $limit = 10): array
    {
        return $this->createQueryBuilder('e')
            ->select('IDENTITY(e.post) as post_id, COUNT(DISTINCT e.sessionId) as engagement_count')
            ->leftJoin('e.post', 'p')
            ->where('p.liveText = :liveText')
            ->setParameter('liveText', $liveText)
            ->groupBy('e.post')
            ->orderBy('engagement_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
