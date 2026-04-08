<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Clustering;

use App\Entity\PressRelease;
use App\Entity\Source;
use App\Entity\StoryCluster;
use App\Enum\PressReleaseStatus;
use App\Enum\StoryClusterStatus;
use App\Repository\AppSettingRepository;
use App\Repository\StoryClusterRepository;
use App\Service\Clustering\AutoPromoteService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class AutoPromoteServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private StoryClusterRepository&MockObject $clusterRepository;
    private AppSettingRepository&MockObject $settingRepository;
    private AutoPromoteService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->clusterRepository = $this->createMock(StoryClusterRepository::class);
        $this->settingRepository = $this->createMock(AppSettingRepository::class);

        $this->service = new AutoPromoteService(
            $this->em,
            $this->clusterRepository,
            $this->settingRepository,
            new NullLogger(),
        );
    }

    private function createCluster(
        float $score = 0.8,
        StoryClusterStatus $status = StoryClusterStatus::AUTO,
        bool $promoted = false,
    ): StoryCluster {
        $cluster = new StoryCluster();
        $cluster->setPrimaryHeadline('Test cluster headline for testing');
        $cluster->setImportanceScore($score);
        $cluster->setStatus($status);
        $cluster->setPromotedToPressRelease($promoted);

        return $cluster;
    }

    // --- isEligible() tests ---

    #[Test]
    public function isEligibleReturnsTrueForHighScoreAutoCluster(): void
    {
        $cluster = $this->createCluster(0.8, StoryClusterStatus::AUTO);

        $this->settingRepository->method('get')->willReturn('0.70');

        $this->assertTrue($this->service->isEligible($cluster));
    }

    #[Test]
    public function isEligibleReturnsFalseForLowScoreCluster(): void
    {
        $cluster = $this->createCluster(0.5, StoryClusterStatus::AUTO);

        $this->settingRepository->method('get')->willReturn('0.70');

        $this->assertFalse($this->service->isEligible($cluster));
    }

    #[Test]
    public function isEligibleReturnsFalseForApprovedCluster(): void
    {
        $cluster = $this->createCluster(0.9, StoryClusterStatus::APPROVED);

        $this->assertTrue($cluster->getImportanceScore() >= 0.7);
        $this->assertFalse($this->service->isEligible($cluster));
    }

    #[Test]
    public function isEligibleReturnsFalseForRejectedCluster(): void
    {
        $cluster = $this->createCluster(0.9, StoryClusterStatus::REJECTED);

        $this->assertFalse($this->service->isEligible($cluster));
    }

    #[Test]
    public function isEligibleReturnsFalseForAlreadyPromotedCluster(): void
    {
        $cluster = $this->createCluster(0.9, StoryClusterStatus::AUTO, promoted: true);

        $this->assertFalse($this->service->isEligible($cluster));
    }

    #[Test]
    public function isEligibleRespectsCustomThreshold(): void
    {
        $cluster = $this->createCluster(0.55, StoryClusterStatus::AUTO);

        $this->assertTrue($this->service->isEligible($cluster, 0.5));
        $this->assertFalse($this->service->isEligible($cluster, 0.6));
    }

    // --- promoteCluster() tests ---

    #[Test]
    public function promoteClusterCreatesPressRelease(): void
    {
        $cluster = $this->createCluster(0.85, StoryClusterStatus::AUTO);
        $cluster->setSummaryMedium('Medium summary text');
        $cluster->setSummaryShort('Short summary');

        $this->em->expects($this->once())->method('persist');

        $pr = $this->service->promoteCluster($cluster);

        $this->assertNotNull($pr);
        $this->assertInstanceOf(PressRelease::class, $pr);
        $this->assertSame(PressReleaseStatus::PENDING, $pr->getStatus());
        $this->assertStringContainsString('Test cluster headline', $pr->getTitle());
        $this->assertSame('Short summary', $pr->getLead());
        $this->assertStringContainsString('Medium summary text', $pr->getContent());
        $this->assertSame(StoryClusterStatus::PROMOTED, $cluster->getStatus());
        $this->assertTrue($cluster->isPromotedToPressRelease());
    }

    #[Test]
    public function promoteClusterReturnsNullForAlreadyPromoted(): void
    {
        $cluster = $this->createCluster(0.9, StoryClusterStatus::PROMOTED, promoted: true);

        $this->em->expects($this->never())->method('persist');

        $pr = $this->service->promoteCluster($cluster);

        $this->assertNull($pr);
    }

    #[Test]
    public function promoteClusterReturnsNullForApprovedStatus(): void
    {
        $cluster = $this->createCluster(0.9, StoryClusterStatus::APPROVED);

        $this->em->expects($this->never())->method('persist');

        $pr = $this->service->promoteCluster($cluster);

        $this->assertNull($pr);
    }

    #[Test]
    public function promoteClusterReturnsNullForRejectedStatus(): void
    {
        $cluster = $this->createCluster(0.9, StoryClusterStatus::REJECTED);

        $this->em->expects($this->never())->method('persist');

        $pr = $this->service->promoteCluster($cluster);

        $this->assertNull($pr);
    }

    #[Test]
    public function promoteClusterAllowsReviewedStatus(): void
    {
        $cluster = $this->createCluster(0.85, StoryClusterStatus::REVIEWED);

        $this->em->expects($this->once())->method('persist');

        $pr = $this->service->promoteCluster($cluster);

        $this->assertNotNull($pr);
        $this->assertSame(StoryClusterStatus::PROMOTED, $cluster->getStatus());
    }

    #[Test]
    public function promoteClusterAssignsHighestCredibilitySource(): void
    {
        $cluster = $this->createCluster(0.85, StoryClusterStatus::AUTO);

        $lowSource = new Source();
        $lowSource->setName('Low Source');
        $lowSource->setCredibilityWeight(0.5);

        $highSource = new Source();
        $highSource->setName('High Source');
        $highSource->setCredibilityWeight(0.95);

        $pr1 = new PressRelease();
        $pr1->setTitle('Low PR');
        $pr1->setContent('Content 1');
        $pr1->setCategorySlug('externe');
        $pr1->setSource($lowSource);

        $pr2 = new PressRelease();
        $pr2->setTitle('High PR');
        $pr2->setContent('Content 2');
        $pr2->setCategorySlug('externe');
        $pr2->setSource($highSource);
        $pr2->setSourceUrl('https://example.com/article');

        $cluster->addPressRelease($pr1);
        $cluster->addPressRelease($pr2);

        $result = $this->service->promoteCluster($cluster);

        $this->assertNotNull($result);
        $this->assertSame($highSource, $result->getSource());
        $this->assertSame('https://example.com/article', $result->getSourceUrl());
    }

    #[Test]
    public function promoteClusterFallsBackToTitlesWithoutSummary(): void
    {
        $cluster = $this->createCluster(0.85, StoryClusterStatus::AUTO);

        $pr = new PressRelease();
        $pr->setTitle('Press release title');
        $pr->setContent('Content');
        $pr->setCategorySlug('externe');
        $cluster->addPressRelease($pr);

        $result = $this->service->promoteCluster($cluster);

        $this->assertNotNull($result);
        $this->assertStringContainsString('Press release title', $result->getContent());
    }

    // --- getThreshold() / setThreshold() tests ---

    #[Test]
    public function getThresholdReturnsDefaultWhenNotSet(): void
    {
        $this->settingRepository->method('get')->willReturn(null);

        $this->assertSame(0.7, $this->service->getThreshold());
    }

    #[Test]
    public function getThresholdReturnsStoredValue(): void
    {
        $this->settingRepository->method('get')->willReturn('0.65');

        $this->assertSame(0.65, $this->service->getThreshold());
    }

    #[Test]
    public function setThresholdClampsValue(): void
    {
        $this->settingRepository->expects($this->once())
            ->method('set')
            ->with('auto_promote_threshold', '1');

        $this->service->setThreshold(1.5);
    }
}
