<?php

declare(strict_types=1);

namespace App\Dto\LiveText;

use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO for LiveText analytics response.
 */
class LiveTextAnalyticsDto
{
    #[Groups(['livetext_analytics:read'])]
    public int $liveTextId;

    #[Groups(['livetext_analytics:read'])]
    public int $totalViews;

    #[Groups(['livetext_analytics:read'])]
    public int $uniqueViewers;

    #[Groups(['livetext_analytics:read'])]
    public float $averageTimeSpent;

    #[Groups(['livetext_analytics:read'])]
    public int $peakConcurrentViewers;

    #[Groups(['livetext_analytics:read'])]
    public int $currentViewers;

    #[Groups(['livetext_analytics:read'])]
    public int $totalPosts;

    #[Groups(['livetext_analytics:read'])]
    public int $totalReactions;

    #[Groups(['livetext_analytics:read'])]
    public ?array $viewsOverTime = null;

    #[Groups(['livetext_analytics:read'])]
    public ?array $postEngagement = null;

    #[Groups(['livetext_analytics:read'])]
    public ?array $viewersByPlatform = null;

    public function __construct(array $data)
    {
        $this->liveTextId = $data['liveTextId'] ?? 0;
        $this->totalViews = $data['totalViews'] ?? 0;
        $this->uniqueViewers = $data['uniqueViewers'] ?? 0;
        $this->averageTimeSpent = $data['averageTimeSpent'] ?? 0.0;
        $this->peakConcurrentViewers = $data['peakConcurrentViewers'] ?? 0;
        $this->currentViewers = $data['currentViewers'] ?? 0;
        $this->totalPosts = $data['totalPosts'] ?? 0;
        $this->totalReactions = $data['totalReactions'] ?? 0;
        $this->viewsOverTime = $data['viewsOverTime'] ?? null;
        $this->postEngagement = $data['postEngagement'] ?? null;
        $this->viewersByPlatform = $data['viewersByPlatform'] ?? null;
    }
}
