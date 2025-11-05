<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\LiveText;
use App\Entity\LiveTextView;
use App\Entity\User;
use App\Repository\LiveTextPostRepository;
use App\Repository\LiveTextReactionRepository;
use App\Repository\LiveTextViewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Service for handling LiveText analytics and metrics.
 */
class LiveTextAnalyticsService
{
    private const REDIS_VIEWERS_KEY_PREFIX = 'live_text:viewers:';

    private const VIEWER_EXPIRY = 300; // 5 minutes in seconds

    private const CACHE_TTL = 60; // Cache analytics for 1 minute

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LiveTextViewRepository $viewRepository,
        private LiveTextPostRepository $postRepository,
        private LiveTextReactionRepository $reactionRepository,
        private CacheInterface $cache,
        private RequestStack $requestStack,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Track a view session (initial view or heartbeat update).
     */
    public function trackView(LiveText $liveText, string $sessionId, ?User $user = null): LiveTextView
    {
        $request = $this->requestStack->getCurrentRequest();

        // Find existing view or create new one
        $view = $this->viewRepository->findByLiveTextAndSession($liveText, $sessionId);

        if ($view === null) {
            $view = new LiveTextView();
            $view->setLiveText($liveText);
            $view->setSessionId($sessionId);
            $view->setUser($user);

            if ($request !== null) {
                $view->setIpAddress($request->getClientIp());
                $view->setUserAgent($request->headers->get('User-Agent'));
            }

            $this->entityManager->persist($view);
        } else {
            // Update last activity
            $view->updateActivity();
        }

        $this->entityManager->flush();

        return $view;
    }

    /**
     * Update time spent for a session.
     */
    public function updateTimeSpent(string $sessionId, LiveText $liveText, int $secondsSpent): void
    {
        $view = $this->viewRepository->findByLiveTextAndSession($liveText, $sessionId);

        if ($view !== null) {
            $view->addTimeSpent($secondsSpent);
            $view->updateActivity();
            $this->entityManager->flush();
        }
    }

    /**
     * Add viewer to Redis set (for real-time count).
     */
    public function addActiveViewer(int $liveTextId, string $sessionId): void
    {
        try {
            $key = self::REDIS_VIEWERS_KEY_PREFIX . $liveTextId;

            // Add to Redis set with expiry
            $redis = $this->cache->get('redis_connection', function (ItemInterface $item) {
                // This is a workaround - in production, inject RedisAdapter directly
                return null;
            });

            // For now, we'll use cache to store viewer count
            // In production, use Predis or PhpRedis directly
            $this->cache->get($key, function (ItemInterface $item) use ($sessionId) {
                $item->expiresAfter(self::VIEWER_EXPIRY);

                return [$sessionId];
            });

        } catch (Exception $e) {
            $this->logger->error('Failed to add active viewer to Redis', [
                'liveTextId' => $liveTextId,
                'sessionId' => $sessionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get current active viewer count from Redis.
     */
    public function getActiveViewerCount(int $liveTextId): int
    {
        try {
            // Get active sessions from database (fallback)
            $liveText = $this->entityManager->getRepository(LiveText::class)->find($liveTextId);

            if ($liveText === null) {
                return 0;
            }

            $activeSessions = $this->viewRepository->getActiveSessions($liveText, 5);

            return \count($activeSessions);

        } catch (Exception $e) {
            $this->logger->error('Failed to get active viewer count', [
                'liveTextId' => $liveTextId,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Get comprehensive analytics for a live text.
     *
     * @return array{
     *     totalViews: int,
     *     uniqueViewers: int,
     *     averageTimeSpent: float,
     *     peakConcurrentViewers: int,
     *     currentViewers: int,
     *     totalPosts: int,
     *     totalReactions: int,
     *     viewsOverTime: array,
     *     postEngagement: array,
     *     viewersByPlatform: array
     * }
     */
    public function getAnalytics(LiveText $liveText, bool $detailed = false): array
    {
        $cacheKey = \sprintf('live_text_analytics_%d_%s', $liveText->getId(), $detailed ? 'detailed' : 'summary');

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($liveText, $detailed) {
            $item->expiresAfter(self::CACHE_TTL);

            $analytics = [
                'totalViews' => $this->viewRepository->getTotalViewsCount($liveText),
                'uniqueViewers' => $this->viewRepository->getUniqueViewersCount($liveText),
                'averageTimeSpent' => round($this->viewRepository->getAverageTimeSpent($liveText), 2),
                'peakConcurrentViewers' => $this->viewRepository->getPeakConcurrentViewers($liveText),
                'currentViewers' => $this->getActiveViewerCount($liveText->getId()),
                'totalPosts' => $this->postRepository->count(['liveText' => $liveText]),
                'totalReactions' => $this->getTotalReactionsCount($liveText),
            ];

            if ($detailed) {
                $analytics['viewsOverTime'] = $this->viewRepository->getViewsOverTime($liveText);
                $analytics['postEngagement'] = $this->getPostEngagement($liveText);
                $analytics['viewersByPlatform'] = $this->viewRepository->getViewersByPlatform($liveText);
            }

            return $analytics;
        });
    }

    /**
     * Clean up old view records (call via cron job).
     */
    public function cleanupOldViews(int $hoursThreshold = 24): int
    {
        return $this->viewRepository->cleanupOldSessions($hoursThreshold);
    }

    /**
     * Generate session ID for anonymous users.
     */
    public function generateSessionId(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Clear analytics cache for a live text.
     */
    public function clearCache(LiveText $liveText): void
    {
        $this->cache->delete(\sprintf('live_text_analytics_%d_summary', $liveText->getId()));
        $this->cache->delete(\sprintf('live_text_analytics_%d_detailed', $liveText->getId()));
    }

    /**
     * Get total reactions count for a live text.
     */
    private function getTotalReactionsCount(LiveText $liveText): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from('App\Entity\LiveTextReaction', 'r')
            ->join('r.liveTextPost', 'p')
            ->where('p.liveText = :liveText')
            ->setParameter('liveText', $liveText)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get post engagement metrics (views and reactions per post).
     *
     * @return array<array{postId: int, title: string, reactionsCount: int}>
     */
    private function getPostEngagement(LiveText $liveText): array
    {
        $posts = $this->postRepository->findBy(
            ['liveText' => $liveText],
            ['publishedAt' => 'DESC'],
            10 // Top 10 posts
        );

        $engagement = [];

        foreach ($posts as $post) {
            $reactionsCount = $this->reactionRepository->count(['liveTextPost' => $post]);

            $engagement[] = [
                'postId' => $post->getId(),
                'content' => mb_substr(strip_tags($post->getContent() ?? ''), 0, 100),
                'reactionsCount' => $reactionsCount,
                'publishedAt' => $post->getPublishedAt()?->format('c'),
            ];
        }

        return $engagement;
    }
}
