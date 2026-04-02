<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\LiveText\LiveTextAnalyticsDto;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LiveTextAnalyticsDtoTest extends TestCase
{
    #[Test]
    public function itInitializesAllPropertiesFromArray(): void
    {
        $data = [
            'liveTextId' => 42,
            'totalViews' => 1000,
            'uniqueViewers' => 750,
            'averageTimeSpent' => 3.5,
            'peakConcurrentViewers' => 200,
            'currentViewers' => 50,
            'totalPosts' => 30,
            'totalReactions' => 450,
            'viewsOverTime' => [['time' => '10:00', 'views' => 100]],
            'postEngagement' => [['postId' => 1, 'reactions' => 50]],
            'viewersByPlatform' => ['web' => 700, 'mobile' => 50],
        ];

        $dto = new LiveTextAnalyticsDto($data);

        $this->assertSame(42, $dto->liveTextId);
        $this->assertSame(1000, $dto->totalViews);
        $this->assertSame(750, $dto->uniqueViewers);
        $this->assertSame(3.5, $dto->averageTimeSpent);
        $this->assertSame(200, $dto->peakConcurrentViewers);
        $this->assertSame(50, $dto->currentViewers);
        $this->assertSame(30, $dto->totalPosts);
        $this->assertSame(450, $dto->totalReactions);
        $this->assertNotNull($dto->viewsOverTime);
        $this->assertNotNull($dto->postEngagement);
        $this->assertNotNull($dto->viewersByPlatform);
    }

    #[Test]
    public function itUsesDefaultValuesForMissingData(): void
    {
        $dto = new LiveTextAnalyticsDto([]);

        $this->assertSame(0, $dto->liveTextId);
        $this->assertSame(0, $dto->totalViews);
        $this->assertSame(0, $dto->uniqueViewers);
        $this->assertSame(0.0, $dto->averageTimeSpent);
        $this->assertSame(0, $dto->peakConcurrentViewers);
        $this->assertSame(0, $dto->currentViewers);
        $this->assertSame(0, $dto->totalPosts);
        $this->assertSame(0, $dto->totalReactions);
        $this->assertNull($dto->viewsOverTime);
        $this->assertNull($dto->postEngagement);
        $this->assertNull($dto->viewersByPlatform);
    }

    #[Test]
    public function itHandlesPartialData(): void
    {
        $dto = new LiveTextAnalyticsDto([
            'liveTextId' => 10,
            'totalViews' => 500,
        ]);

        $this->assertSame(10, $dto->liveTextId);
        $this->assertSame(500, $dto->totalViews);
        $this->assertSame(0, $dto->uniqueViewers);
        $this->assertNull($dto->viewsOverTime);
    }

    #[Test]
    public function itCorrectlySetsOptionalArrayFields(): void
    {
        $viewsOverTime = [
            ['time' => '10:00', 'views' => 100],
            ['time' => '11:00', 'views' => 150],
        ];

        $dto = new LiveTextAnalyticsDto([
            'viewsOverTime' => $viewsOverTime,
        ]);

        $this->assertSame($viewsOverTime, $dto->viewsOverTime);
    }

    #[Test]
    public function itHandlesZeroValuesCorrectly(): void
    {
        $dto = new LiveTextAnalyticsDto([
            'liveTextId' => 0,
            'totalViews' => 0,
            'averageTimeSpent' => 0.0,
        ]);

        $this->assertSame(0, $dto->liveTextId);
        $this->assertSame(0, $dto->totalViews);
        $this->assertSame(0.0, $dto->averageTimeSpent);
    }
}
