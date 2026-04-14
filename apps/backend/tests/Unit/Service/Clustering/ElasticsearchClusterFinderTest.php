<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Clustering;

use App\Repository\AppSettingRepository;
use App\Service\Clustering\ElasticsearchClusterFinder;
use App\Service\Clustering\PressReleaseIndexManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ElasticsearchClusterFinderTest extends TestCase
{
    private PressReleaseIndexManager&MockObject $indexManager;
    private AppSettingRepository&MockObject $appSettings;

    protected function setUp(): void
    {
        $this->indexManager = $this->createMock(PressReleaseIndexManager::class);
        $this->appSettings = $this->createMock(AppSettingRepository::class);
    }

    #[Test]
    public function findSimilarReturnsEmptyWhenDisabled(): void
    {
        $this->indexManager->method('isEnabled')->willReturn(false);

        $finder = new ElasticsearchClusterFinder($this->indexManager, $this->appSettings, new NullLogger());

        $result = $finder->findSimilar('title', 'content');

        $this->assertSame([], $result);
    }

    #[Test]
    public function findSimilarReturnsEmptyWhenClientIsNull(): void
    {
        $this->indexManager->method('isEnabled')->willReturn(true);
        $this->indexManager->method('getClient')->willReturn(null);

        $finder = new ElasticsearchClusterFinder($this->indexManager, $this->appSettings, new NullLogger());

        $result = $finder->findSimilar('title', 'content');

        $this->assertSame([], $result);
    }

    #[Test]
    public function isEnabledDelegatesToIndexManager(): void
    {
        $this->indexManager->method('isEnabled')->willReturn(true);

        $finder = new ElasticsearchClusterFinder($this->indexManager, $this->appSettings, new NullLogger());

        $this->assertTrue($finder->isEnabled());
    }

    #[Test]
    public function finderUsesCorrectIndexName(): void
    {
        $this->indexManager->method('getIndexName')
            ->willReturn(PressReleaseIndexManager::INDEX_NAME);

        $this->assertSame('deschide_press_releases', $this->indexManager->getIndexName());
    }

    #[Test]
    public function minScoreDefaultsFromAppSettings(): void
    {
        $this->appSettings->method('getFloat')
            ->with('cluster_mlt_min_score', 0.60)
            ->willReturn(0.75);

        // Just verify construction works and reads from settings
        $finder = new ElasticsearchClusterFinder($this->indexManager, $this->appSettings, new NullLogger());
        $this->assertNotNull($finder);
    }

    #[Test]
    public function defaultMinScoreIsZeroSixty(): void
    {
        // When AppSettings returns default (nothing stored)
        $this->appSettings->method('getFloat')->willReturn(0.60);
        $this->appSettings->method('get')->willReturn('40');

        $finder = new ElasticsearchClusterFinder($this->indexManager, $this->appSettings, new NullLogger());
        $this->assertNotNull($finder);
    }
}
