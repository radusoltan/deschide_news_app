<?php

declare(strict_types=1);

namespace App\Controller;

use App\Message\PageViewEvent;
use App\Service\PerformanceService;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Annotation\Route;

class TrackingController extends AbstractController
{
    public function __construct(
        private readonly PerformanceService $performance,
        private readonly MessageBusInterface $messageBus
    ) {
    }

    #[Route('/api/track/pageview', name: 'track_pageview', methods: ['POST'])]
    public function trackPageview(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['article_id'], $data['visitor_id'])) {
            return new JsonResponse(['error' => 'Invalid request'], 400);
        }

        $articleId = (int) $data['article_id'];
        $visitorId = $data['visitor_id'];

        // Increment counters (sync - fast)
        $this->performance->incrementArticleViews($articleId);
        $this->performance->trackUniqueVisitor($articleId, $visitorId);
        $this->performance->trackSiteVisitor($visitorId);

        // Dispatch async event for detailed tracking
        $this->messageBus->dispatch(new PageViewEvent(
            articleId: $articleId,
            visitorId: $visitorId,
            ipAddress: $request->getClientIp(),
            userAgent: $request->headers->get('User-Agent'),
            referrer: $request->headers->get('Referer'),
            timestamp: new DateTimeImmutable(),
            categoryId: $data['category_id'] ?? null
        ));

        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/track/reading-time', name: 'track_reading_time', methods: ['POST'])]
    public function trackReadingTime(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['article_id'], $data['visitor_id'], $data['reading_time'])) {
            return new JsonResponse(['error' => 'Invalid request'], 400);
        }

        // Store reading time for aggregation
        $key = "reading_time:{$data['article_id']}:{$data['visitor_id']}";
        $this->performance->setCached($key, $data['reading_time'], 86400); // 24h

        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/track/scroll-depth', name: 'track_scroll_depth', methods: ['POST'])]
    public function trackScrollDepth(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['article_id'], $data['visitor_id'], $data['scroll_depth'])) {
            return new JsonResponse(['error' => 'Invalid request'], 400);
        }

        // Track completion (100% scroll)
        if ($data['scroll_depth'] === 100) {
            $key = "completed:{$data['article_id']}:{$data['visitor_id']}";
            $this->performance->setCached($key, 1, 86400);
        }

        return new JsonResponse(['success' => true]);
    }

    #[Route('/api/stats/article/{id}', name: 'get_article_stats', methods: ['GET'])]
    public function getArticleStats(int $id): JsonResponse
    {
        $views = $this->performance->getArticleViews($id);

        return new JsonResponse([
            'article_id' => $id,
            'views' => $views,
        ]);
    }

    #[Route('/api/stats/trending', name: 'get_trending', methods: ['GET'])]
    public function getTrending(Request $request): JsonResponse
    {
        $limit = (int) $request->query->get('limit', 10);
        $trending = $this->performance->getTrendingArticles($limit);

        return new JsonResponse([
            'trending' => $trending,
        ]);
    }

    #[Route('/api/stats/site', name: 'get_site_stats', methods: ['GET'])]
    public function getSiteStats(Request $request): JsonResponse
    {
        $date = $request->query->get('date', date('Y-m-d'));
        $uniqueVisitors = $this->performance->getUniqueVisitorCount($date);

        return new JsonResponse([
            'date' => $date,
            'unique_visitors' => $uniqueVisitors,
        ]);
    }
}
