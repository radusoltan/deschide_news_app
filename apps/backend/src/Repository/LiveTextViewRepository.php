<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\LiveText;
use App\Entity\LiveTextView;
use DateTime;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LiveTextView>
 */
class LiveTextViewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LiveTextView::class);
    }

    /**
     * Find a view by live text and session ID.
     */
    public function findByLiveTextAndSession(LiveText $liveText, string $sessionId): ?LiveTextView
    {
        return $this->createQueryBuilder('v')
            ->andWhere('v.liveText = :liveText')
            ->andWhere('v.sessionId = :sessionId')
            ->setParameter('liveText', $liveText)
            ->setParameter('sessionId', $sessionId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get total views count for a live text.
     */
    public function getTotalViewsCount(LiveText $liveText): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->andWhere('v.liveText = :liveText')
            ->setParameter('liveText', $liveText)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get unique viewers count for a live text (by IP address).
     */
    public function getUniqueViewersCount(LiveText $liveText): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(DISTINCT v.ipAddress)')
            ->andWhere('v.liveText = :liveText')
            ->andWhere('v.ipAddress IS NOT NULL')
            ->setParameter('liveText', $liveText)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get average time spent for a live text.
     */
    public function getAverageTimeSpent(LiveText $liveText): float
    {
        $result = $this->createQueryBuilder('v')
            ->select('AVG(v.timeSpent)')
            ->andWhere('v.liveText = :liveText')
            ->andWhere('v.timeSpent > 0')
            ->setParameter('liveText', $liveText)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (float) $result : 0.0;
    }

    /**
     * Get peak concurrent viewers (maximum viewers in any 5-minute window).
     */
    public function getPeakConcurrentViewers(LiveText $liveText): int
    {
        // Use native SQL for PostgreSQL-specific date formatting
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT COUNT(DISTINCT session_id) as viewer_count
            FROM live_text_views
            WHERE live_text_id = :liveTextId
            GROUP BY TO_CHAR(viewed_at, 'YYYY-MM-DD HH24:MI')
            ORDER BY viewer_count DESC
            LIMIT 1
        ";

        $result = $conn->executeQuery($sql, [
            'liveTextId' => $liveText->getId(),
        ])->fetchAssociative();

        return $result !== false ? (int) $result['viewer_count'] : 0;
    }

    /**
     * Get views over time (grouped by hour) for charts.
     *
     * @return array<array{hour: string, count: int}>
     */
    public function getViewsOverTime(LiveText $liveText, ?DateTimeInterface $since = null): array
    {
        // Use native SQL for PostgreSQL-specific date formatting
        $conn = $this->getEntityManager()->getConnection();

        $sql = "
            SELECT
                TO_CHAR(viewed_at, 'YYYY-MM-DD HH24:00:00') as hour,
                COUNT(id) as count
            FROM live_text_views
            WHERE live_text_id = :liveTextId
        ";

        $params = ['liveTextId' => $liveText->getId()];

        if ($since !== null) {
            $sql .= ' AND viewed_at >= :since';
            $params['since'] = $since->format('Y-m-d H:i:s');
        }

        $sql .= ' GROUP BY hour ORDER BY hour ASC';

        return $conn->executeQuery($sql, $params)->fetchAllAssociative();
    }

    /**
     * Get active sessions (activity within last 5 minutes).
     *
     * @return array<LiveTextView>
     */
    public function getActiveSessions(LiveText $liveText, int $minutesThreshold = 5): array
    {
        $threshold = new DateTime();
        $threshold->modify("-{$minutesThreshold} minutes");

        return $this->createQueryBuilder('v')
            ->andWhere('v.liveText = :liveText')
            ->andWhere('v.lastActivityAt >= :threshold')
            ->setParameter('liveText', $liveText)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
    }

    /**
     * Clean up old inactive sessions (older than specified hours).
     */
    public function cleanupOldSessions(int $hoursThreshold = 24): int
    {
        $threshold = new DateTime();
        $threshold->modify("-{$hoursThreshold} hours");

        return $this->createQueryBuilder('v')
            ->delete()
            ->andWhere('v.lastActivityAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->execute();
    }

    /**
     * Get viewer count by device/platform (based on user agent).
     *
     * @return array<array{platform: string, count: int}>
     */
    public function getViewersByPlatform(LiveText $liveText): array
    {
        // Simplified platform detection based on user agent
        $results = $this->createQueryBuilder('v')
            ->select('v.userAgent')
            ->addSelect('COUNT(v.id) as count')
            ->andWhere('v.liveText = :liveText')
            ->andWhere('v.userAgent IS NOT NULL')
            ->setParameter('liveText', $liveText)
            ->groupBy('v.userAgent')
            ->getQuery()
            ->getResult();

        // Categorize user agents into platforms
        $platforms = [
            'Mobile' => 0,
            'Desktop' => 0,
            'Tablet' => 0,
            'Other' => 0,
        ];

        foreach ($results as $result) {
            $userAgent = strtolower($result['userAgent'] ?? '');
            $count = (int) $result['count'];

            if (str_contains($userAgent, 'mobile') || str_contains($userAgent, 'android')) {
                $platforms['Mobile'] += $count;
            } elseif (str_contains($userAgent, 'tablet') || str_contains($userAgent, 'ipad')) {
                $platforms['Tablet'] += $count;
            } elseif (str_contains($userAgent, 'mozilla') || str_contains($userAgent, 'chrome') || str_contains($userAgent, 'safari')) {
                $platforms['Desktop'] += $count;
            } else {
                $platforms['Other'] += $count;
            }
        }

        return array_map(fn ($platform, $count) => ['platform' => $platform, 'count' => $count], array_keys($platforms), $platforms);
    }
}
