<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Clustering;

use App\Service\Clustering\ElasticsearchClusterFinder;
use App\Service\Clustering\PressReleaseIndexManager;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ElasticsearchClusterFinderTest extends TestCase
{
    private PressReleaseIndexManager&MockObject $indexManager;

    protected function setUp(): void
    {
        $this->indexManager = $this->createMock(PressReleaseIndexManager::class);
    }

    #[Test]
    public function findSimilarReturnsEmptyWhenDisabled(): void
    {
        $this->indexManager->method('isEnabled')->willReturn(false);

        $finder = new ElasticsearchClusterFinder($this->indexManager, new NullLogger());

        $result = $finder->findSimilar('title', 'content');

        $this->assertSame([], $result);
    }

    #[Test]
    public function findSimilarReturnsEmptyWhenClientIsNull(): void
    {
        $this->indexManager->method('isEnabled')->willReturn(true);
        $this->indexManager->method('getClient')->willReturn(null);

        $finder = new ElasticsearchClusterFinder($this->indexManager, new NullLogger());

        $result = $finder->findSimilar('title', 'content');

        $this->assertSame([], $result);
    }

    #[Test]
    public function isEnabledDelegatesToIndexManager(): void
    {
        $this->indexManager->method('isEnabled')->willReturn(true);

        $finder = new ElasticsearchClusterFinder($this->indexManager, new NullLogger());

        $this->assertTrue($finder->isEnabled());
    }

    #[Test]
    public function finderUsesCorrectIndexName(): void
    {
        $this->indexManager->method('getIndexName')
            ->willReturn(PressReleaseIndexManager::INDEX_NAME);

        $this->assertSame('deschide_press_releases', $this->indexManager->getIndexName());
    }
}
