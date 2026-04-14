<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Clustering;

use App\Entity\PressRelease;
use App\Entity\StoryCluster;
use App\Repository\AppSettingRepository;
use App\Repository\StoryClusterRepository;
use App\Service\Clustering\ClusteringService;
use App\Service\Clustering\ElasticsearchClusterFinder;
use App\Service\Clustering\PressReleaseIndexer;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ClusteringServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private StoryClusterRepository&MockObject $clusterRepo;
    private ElasticsearchClusterFinder&MockObject $clusterFinder;
    private PressReleaseIndexer&MockObject $indexer;
    private AppSettingRepository&MockObject $appSettings;
    private ClusteringService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->clusterRepo = $this->createMock(StoryClusterRepository::class);
        $this->clusterFinder = $this->createMock(ElasticsearchClusterFinder::class);
        $this->indexer = $this->createMock(PressReleaseIndexer::class);
        $this->appSettings = $this->createMock(AppSettingRepository::class);
        $this->appSettings->method('getInt')->willReturn(48);

        $this->service = new ClusteringService(
            $this->em,
            $this->clusterRepo,
            $this->clusterFinder,
            $this->indexer,
            $this->appSettings,
            new NullLogger(),
        );
    }

    #[Test]
    public function noUnclusteredPressReleasesReturnsZero(): void
    {
        $this->clusterRepo->method('findUnclusteredPressReleaseIds')->willReturn([]);

        $result = $this->service->clusterNewPressReleases();

        $this->assertSame(0, $result);
    }

    #[Test]
    public function newPressReleaseCreatesNewCluster(): void
    {
        $pr = $this->createPressRelease(1, 'EU sanctions on Russia', 'Full content about EU sanctions');

        $this->clusterRepo->method('findUnclusteredPressReleaseIds')->willReturn([1]);
        $this->em->method('find')->willReturn($pr);
        $this->clusterFinder->method('findSimilar')->willReturn([]);

        $this->em->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(StoryCluster::class));
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->clusterNewPressReleases();

        $this->assertSame(1, $result);
    }

    #[Test]
    public function similarPressReleaseJoinsExistingCluster(): void
    {
        $existingPr = $this->createPressRelease(1, 'EU announces sanctions', 'Content about EU sanctions');
        $newPr = $this->createPressRelease(2, 'EU sanctions target Russia energy', 'Content about EU sanctions on energy');

        $cluster = new StoryCluster();
        $cluster->setPrimaryHeadline('EU announces sanctions');
        $cluster->addPressRelease($existingPr);
        $cluster->setFirstSeenAt(new \DateTimeImmutable('-1 hour'));

        $this->clusterRepo->method('findUnclusteredPressReleaseIds')->willReturn([2]);
        $this->em->method('find')->willReturn($newPr);
        $this->clusterFinder->method('findSimilar')->willReturn([
            ['score' => 0.85, 'pressReleaseId' => 1, 'title' => 'EU announces sanctions'],
        ]);
        $this->clusterRepo->method('findClustersContainingPressReleases')->willReturn([$cluster]);

        // Should NOT persist new cluster (adds to existing)
        $this->em->expects($this->never())->method('persist');

        $result = $this->service->clusterNewPressReleases();

        $this->assertSame(1, $result);
        $this->assertCount(2, $cluster->getPressReleases());
    }

    #[Test]
    public function temporallyDistantArticlesDoNotCluster(): void
    {
        $existingPr = $this->createPressRelease(1, 'EU sanctions update', 'Content');
        $newPr = $this->createPressRelease(2, 'EU sanctions update', 'Same content');
        // Set receivedAt to 3 days ago (beyond 48h window)
        $newPr->setReceivedAt(new \DateTimeImmutable('-72 hours'));

        $cluster = new StoryCluster();
        $cluster->setPrimaryHeadline('EU sanctions update');
        $cluster->addPressRelease($existingPr);
        $cluster->setFirstSeenAt(new \DateTimeImmutable()); // now

        $this->clusterRepo->method('findUnclusteredPressReleaseIds')->willReturn([2]);
        $this->em->method('find')->willReturn($newPr);
        $this->clusterFinder->method('findSimilar')->willReturn([
            ['score' => 0.90, 'pressReleaseId' => 1, 'title' => 'EU sanctions update'],
        ]);
        $this->clusterRepo->method('findClustersContainingPressReleases')->willReturn([$cluster]);

        // Should create NEW cluster since temporal window exceeded
        $this->em->expects($this->once())->method('persist');

        $result = $this->service->clusterNewPressReleases();

        $this->assertSame(1, $result);
    }

    #[Test]
    public function differentArticlesCreateSeparateClusters(): void
    {
        $pr1 = $this->createPressRelease(1, 'EU sanctions on Russia', 'Sanctions content');
        $pr2 = $this->createPressRelease(2, 'FIFA World Cup 2026 qualifiers', 'Football content');

        $this->clusterRepo->method('findUnclusteredPressReleaseIds')->willReturn([1, 2]);
        $this->em->method('find')->willReturnCallback(
            fn (string $class, int $id) => match ($id) {
                1 => $pr1,
                2 => $pr2,
                default => null,
            },
        );
        $this->clusterFinder->method('findSimilar')->willReturn([]);

        // Two separate clusters should be persisted
        $this->em->expects($this->exactly(2))->method('persist');

        $result = $this->service->clusterNewPressReleases();

        $this->assertSame(2, $result);
    }

    #[Test]
    public function clusterGrowthUpdatesCountsCorrectly(): void
    {
        $pr1 = $this->createPressRelease(1, 'Breaking: earthquake', 'Content');
        $pr1->setSourcePublisherDomain('reuters.com');
        $pr2 = $this->createPressRelease(2, 'Major earthquake hits', 'Content');
        $pr2->setSourcePublisherDomain('bbc.co.uk');
        $pr3 = $this->createPressRelease(3, 'Earthquake update', 'Content');
        $pr3->setSourcePublisherDomain('reuters.com');

        $cluster = new StoryCluster();
        $cluster->setPrimaryHeadline('Breaking: earthquake');
        $cluster->addPressRelease($pr1);
        $cluster->addPressRelease($pr2);
        $cluster->recalculateCounts();
        $cluster->setFirstSeenAt(new \DateTimeImmutable('-1 hour'));

        $this->assertSame(2, $cluster->getArticleCount());
        $this->assertSame(2, $cluster->getSourceCount());

        // Add third PR
        $cluster->addPressRelease($pr3);
        $cluster->recalculateCounts();

        $this->assertSame(3, $cluster->getArticleCount());
        $this->assertSame(2, $cluster->getSourceCount()); // reuters appears twice
    }

    #[Test]
    public function dryRunDoesNotPersist(): void
    {
        $pr = $this->createPressRelease(1, 'Test article', 'Content');

        $this->clusterRepo->method('findUnclusteredPressReleaseIds')->willReturn([1]);
        $this->em->method('find')->willReturn($pr);
        $this->clusterFinder->method('findSimilar')->willReturn([]);

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $result = $this->service->clusterNewPressReleases(dryRun: true);

        $this->assertSame(1, $result);
    }

    #[Test]
    public function nullPressReleaseIsSkipped(): void
    {
        $this->clusterRepo->method('findUnclusteredPressReleaseIds')->willReturn([999]);
        $this->em->method('find')->willReturn(null);

        $result = $this->service->clusterNewPressReleases();

        $this->assertSame(0, $result);
    }

    #[Test]
    public function indexerIsCalledForEachPressRelease(): void
    {
        $pr = $this->createPressRelease(1, 'Test', 'Content');

        $this->clusterRepo->method('findUnclusteredPressReleaseIds')->willReturn([1]);
        $this->em->method('find')->willReturn($pr);
        $this->clusterFinder->method('findSimilar')->willReturn([]);

        $this->indexer->expects($this->once())->method('index')->with($pr);

        $this->service->clusterNewPressReleases();
    }

    #[Test]
    public function emptyEsMatchesReturnNoCluster(): void
    {
        $pr = $this->createPressRelease(1, 'Test article', 'Content');

        $this->clusterFinder->method('findSimilar')->willReturn([]);

        $result = $this->service->clusterSinglePressRelease($pr);

        // New cluster should be created
        $this->assertInstanceOf(StoryCluster::class, $result);
        $this->assertSame('Test article', $result->getPrimaryHeadline());
    }

    #[Test]
    public function esMatchWithNoClusterCreatesNew(): void
    {
        $pr = $this->createPressRelease(1, 'Test article', 'Content');

        $this->clusterFinder->method('findSimilar')->willReturn([
            ['score' => 0.5, 'pressReleaseId' => 99, 'title' => 'Similar'],
        ]);
        $this->clusterRepo->method('findClustersContainingPressReleases')->willReturn([]);

        $result = $this->service->clusterSinglePressRelease($pr);

        // No cluster contains PR #99, so a new cluster should be created
        $this->assertInstanceOf(StoryCluster::class, $result);
    }

    private function createPressRelease(int $id, string $title, string $content): PressRelease
    {
        $pr = new PressRelease();
        $pr->setTitle($title);
        $pr->setContent($content);
        $pr->setCategorySlug('extern');

        // Use reflection to set the id
        $ref = new \ReflectionProperty(PressRelease::class, 'id');
        $ref->setValue($pr, $id);

        return $pr;
    }
}
