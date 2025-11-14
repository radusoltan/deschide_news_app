<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\LiveText;
use App\Entity\LiveTextPost;
use App\Entity\LiveTextPostEngagement;
use App\Entity\User;
use App\Repository\LiveTextPostEngagementRepository;
use DateTimeInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Advanced Analytics Service.
 *
 * Provides heatmap data, funnel analysis, and engagement tracking for LiveText
 */
class LiveTextAdvancedAnalyticsService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LiveTextPostEngagementRepository $engagementRepository,
        private RequestStack $requestStack,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Track post engagement event.
     */
    public function trackEngagement(
        LiveTextPost $post,
        string $engagementType,
        ?User $user = null,
        ?int $timeSpent = null,
        ?int $scrollDepth = null,
        ?string $clickedElement = null,
        ?array $metadata = null
    ): LiveTextPostEngagement {
        $request = $this->requestStack->getCurrentRequest();
        $sessionId = $this->getOrCreateSessionId($request);

        $engagement = new LiveTextPostEngagement();
        $engagement->setPost($post);
        $engagement->setSessionId($sessionId);
        $engagement->setUser($user);
        $engagement->setEngagementType($engagementType);
        $engagement->setTimeSpent($timeSpent);
        $engagement->setScrollDepth($scrollDepth);
        $engagement->setClickedElement($clickedElement);
        $engagement->setMetadata($metadata);

        if ($request) {
            $engagement->setIpAddress($request->getClientIp());
            $engagement->setUserAgent($request->headers->get('User-Agent'));
        }

        $this->entityManager->persist($engagement);
        $this->entityManager->flush();

        $this->logger->info('Engagement tracked', [
            'post_id' => $post->getId(),
            'type' => $engagementType,
            'session_id' => $sessionId,
        ]);

        return $engagement;
    }

    /**
     * Get heatmap data for LiveText
     * Returns engagement intensity per post.
     */
    public function getHeatmapData(LiveText $liveText): array
    {
        $rawData = $this->engagementRepository->getHeatmapData($liveText);

        // Process and enrich data
        $heatmapData = [];
        $maxEngagements = 0;

        foreach ($rawData as $row) {
            $totalEngagements = (int) $row['total_engagements'];
            if ($totalEngagements > $maxEngagements) {
                $maxEngagements = $totalEngagements;
            }

            $heatmapData[] = [
                'post_id' => (int) $row['post_id'],
                'unique_engagements' => (int) $row['unique_engagements'],
                'total_engagements' => $totalEngagements,
                'avg_time_spent' => $row['avg_time_spent'] ? round((float) $row['avg_time_spent'], 2) : null,
                'avg_scroll_depth' => $row['avg_scroll_depth'] ? round((float) $row['avg_scroll_depth'], 2) : null,
            ];
        }

        // Calculate intensity (0-100 scale)
        foreach ($heatmapData as &$item) {
            $item['intensity'] = $maxEngagements > 0
                ? round(($item['total_engagements'] / $maxEngagements) * 100, 2)
                : 0;
        }

        return [
            'data' => $heatmapData,
            'max_engagements' => $maxEngagements,
            'total_posts' => \count($heatmapData),
        ];
    }

    /**
     * Get engagement funnel for LiveText
     * Returns conversion rates through engagement stages.
     */
    public function getEngagementFunnel(LiveText $liveText): array
    {
        $rawData = $this->engagementRepository->getEngagementFunnel($liveText);

        // Organize by engagement type
        $funnelStages = [
            'view' => ['unique_users' => 0, 'total_events' => 0, 'conversion_rate' => 100.0],
            'read' => ['unique_users' => 0, 'total_events' => 0, 'conversion_rate' => 0.0],
            'click' => ['unique_users' => 0, 'total_events' => 0, 'conversion_rate' => 0.0],
            'reaction' => ['unique_users' => 0, 'total_events' => 0, 'conversion_rate' => 0.0],
            'share' => ['unique_users' => 0, 'total_events' => 0, 'conversion_rate' => 0.0],
        ];

        foreach ($rawData as $row) {
            $type = $row['engagement_type'];
            if (isset($funnelStages[$type])) {
                $funnelStages[$type]['unique_users'] = (int) $row['unique_users'];
                $funnelStages[$type]['total_events'] = (int) $row['total_events'];
            }
        }

        // Calculate conversion rates
        $viewUsers = $funnelStages['view']['unique_users'];
        if ($viewUsers > 0) {
            $funnelStages['read']['conversion_rate'] = round(($funnelStages['read']['unique_users'] / $viewUsers) * 100, 2);
            $funnelStages['click']['conversion_rate'] = round(($funnelStages['click']['unique_users'] / $viewUsers) * 100, 2);
            $funnelStages['reaction']['conversion_rate'] = round(($funnelStages['reaction']['unique_users'] / $viewUsers) * 100, 2);
            $funnelStages['share']['conversion_rate'] = round(($funnelStages['share']['unique_users'] / $viewUsers) * 100, 2);
        }

        return [
            'funnel' => $funnelStages,
            'total_views' => $viewUsers,
            'drop_off_rate' => [
                'view_to_read' => $this->calculateDropOffRate($funnelStages['view']['unique_users'], $funnelStages['read']['unique_users']),
                'read_to_click' => $this->calculateDropOffRate($funnelStages['read']['unique_users'], $funnelStages['click']['unique_users']),
                'click_to_reaction' => $this->calculateDropOffRate($funnelStages['click']['unique_users'], $funnelStages['reaction']['unique_users']),
                'reaction_to_share' => $this->calculateDropOffRate($funnelStages['reaction']['unique_users'], $funnelStages['share']['unique_users']),
            ],
        ];
    }

    /**
     * Get top engaged posts.
     */
    public function getTopEngagedPosts(LiveText $liveText, int $limit = 10): array
    {
        $rawData = $this->engagementRepository->getTopEngagedPosts($liveText, $limit);

        $topPosts = [];
        foreach ($rawData as $row) {
            $topPosts[] = [
                'post_id' => (int) $row['post_id'],
                'engagement_count' => (int) $row['engagement_count'],
            ];
        }

        return $topPosts;
    }

    /**
     * Get engagement summary for LiveText.
     */
    public function getEngagementSummary(LiveText $liveText): array
    {
        $heatmapData = $this->getHeatmapData($liveText);
        $funnelData = $this->getEngagementFunnel($liveText);
        $topPosts = $this->getTopEngagedPosts($liveText, 5);

        // Calculate overall metrics
        $totalEngagements = array_sum(array_column($heatmapData['data'], 'total_engagements'));
        $totalUniqueUsers = $funnelData['total_views'];
        $avgTimeSpent = 0;
        $avgScrollDepth = 0;

        if (\count($heatmapData['data']) > 0) {
            $validTimeSpent = array_filter(array_column($heatmapData['data'], 'avg_time_spent'));
            $avgTimeSpent = \count($validTimeSpent) > 0 ? round(array_sum($validTimeSpent) / \count($validTimeSpent), 2) : 0;

            $validScrollDepth = array_filter(array_column($heatmapData['data'], 'avg_scroll_depth'));
            $avgScrollDepth = \count($validScrollDepth) > 0 ? round(array_sum($validScrollDepth) / \count($validScrollDepth), 2) : 0;
        }

        return [
            'summary' => [
                'total_engagements' => $totalEngagements,
                'total_unique_users' => $totalUniqueUsers,
                'avg_time_spent' => $avgTimeSpent,
                'avg_scroll_depth' => $avgScrollDepth,
                'engagement_rate' => $totalUniqueUsers > 0
                    ? round($totalEngagements / $totalUniqueUsers, 2)
                    : 0,
            ],
            'funnel' => $funnelData,
            'top_posts' => $topPosts,
            'heatmap_summary' => [
                'max_engagements' => $heatmapData['max_engagements'],
                'total_posts' => $heatmapData['total_posts'],
            ],
        ];
    }

    /**
     * Track post view.
     */
    public function trackView(LiveTextPost $post, ?User $user = null): void
    {
        $this->trackEngagement($post, 'view', $user);
    }

    /**
     * Track post read (user spent time reading).
     */
    public function trackRead(LiveTextPost $post, int $timeSpent, int $scrollDepth, ?User $user = null): void
    {
        $this->trackEngagement($post, 'read', $user, $timeSpent, $scrollDepth);
    }

    /**
     * Track post click.
     */
    public function trackClick(LiveTextPost $post, string $clickedElement, ?User $user = null): void
    {
        $this->trackEngagement($post, 'click', $user, null, null, $clickedElement);
    }

    /**
     * Track post reaction.
     */
    public function trackReaction(LiveTextPost $post, ?User $user = null, ?array $metadata = null): void
    {
        $this->trackEngagement($post, 'reaction', $user, null, null, null, $metadata);
    }

    /**
     * Track post share.
     */
    public function trackShare(LiveTextPost $post, string $platform, ?User $user = null): void
    {
        $this->trackEngagement($post, 'share', $user, null, null, null, ['platform' => $platform]);
    }

    /**
     * Clear old engagement data (for GDPR compliance or data retention).
     */
    public function clearOldEngagements(DateTimeInterface $before): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete(LiveTextPostEngagement::class, 'e')
            ->where('e.createdAt < :before')
            ->setParameter('before', $before);

        $deletedCount = $qb->getQuery()->execute();

        $this->logger->info('Cleared old engagement data', [
            'before' => $before->format('Y-m-d H:i:s'),
            'deleted_count' => $deletedCount,
        ]);

        return $deletedCount;
    }

    /**
     * Get or create session ID for anonymous tracking.
     */
    private function getOrCreateSessionId($request): string
    {
        if (!$request) {
            return bin2hex(random_bytes(16));
        }

        $session = $request->getSession();
        if (!$session->has('analytics_session_id')) {
            $session->set('analytics_session_id', bin2hex(random_bytes(16)));
        }

        return $session->get('analytics_session_id');
    }

    /**
     * Calculate drop-off rate between two stages.
     */
    private function calculateDropOffRate(int $fromCount, int $toCount): float
    {
        if ($fromCount === 0) {
            return 0.0;
        }

        $dropOff = $fromCount - $toCount;

        return round(($dropOff / $fromCount) * 100, 2);
    }
}
