<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\MetricsService;
use App\Service\PerformanceService;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MetricsController extends AbstractController
{
    public function __construct(
        private readonly MetricsService $metrics,
        private readonly PerformanceService $performance
    ) {
    }

    #[Route('/metrics', name: 'metrics', methods: ['GET'])]
    public function metrics(): Response
    {
        // Update dynamic metrics before rendering
        $this->updateDynamicMetrics();

        // Render Prometheus format
        $output = $this->metrics->renderMetrics();

        return new Response($output, 200, [
            'Content-Type' => 'text/plain; version=0.0.4; charset=utf-8',
        ]);
    }

    private function updateDynamicMetrics(): void
    {
        try {
            // Update cache hit rate
            $this->metrics->updateCacheHitRate('redis');

            // Update active sessions count
            $activeSessions = $this->metrics->getActiveSessionCount();
            $this->metrics->setActiveSessionsCount($activeSessions);

            // Update unique visitors today
            $today = date('Y-m-d');
            $uniqueVisitors = $this->performance->getUniqueVisitorCount($today);
            $this->metrics->setUniqueVisitorsCount($today, $uniqueVisitors);

            // Update trending articles (top 10)
            $trending = $this->performance->getTrendingArticles(10);
            foreach ($trending as $item) {
                $this->metrics->setArticleViews($item['article_id'], $item['views']);
            }
        } catch (Exception $e) {
            // Log error but don't fail metrics endpoint
            // Metrics will be incomplete but Prometheus can still scrape
        }
    }
}
