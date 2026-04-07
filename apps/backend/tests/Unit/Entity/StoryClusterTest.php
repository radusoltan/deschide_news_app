<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\PressRelease;
use App\Entity\StoryCluster;
use App\Entity\Topic;
use App\Enum\StoryClusterStatus;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class StoryClusterTest extends TestCase
{
    #[Test]
    public function itInitializesWithDefaults(): void
    {
        $cluster = new StoryCluster();

        $this->assertNull($cluster->getId());
        $this->assertSame(0.0, $cluster->getImportanceScore());
        $this->assertSame(0, $cluster->getSourceCount());
        $this->assertSame(0, $cluster->getArticleCount());
        $this->assertSame(StoryClusterStatus::AUTO, $cluster->getStatus());
        $this->assertFalse($cluster->isPromotedToPressRelease());
        $this->assertSame(1.0, $cluster->getEditorialBoost());
        $this->assertNull($cluster->getSummaryShort());
        $this->assertNull($cluster->getSummaryMedium());
        $this->assertNull($cluster->getWhyItMatters());
        $this->assertNull($cluster->getKeyFacts());
        $this->assertNull($cluster->getRegionTags());
        $this->assertInstanceOf(ArrayCollection::class, $cluster->getPressReleases());
        $this->assertInstanceOf(ArrayCollection::class, $cluster->getTopics());
        $this->assertCount(0, $cluster->getPressReleases());
        $this->assertCount(0, $cluster->getTopics());
        $this->assertInstanceOf(\DateTimeImmutable::class, $cluster->getFirstSeenAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $cluster->getLastUpdatedAt());
    }

    #[Test]
    public function itSetsAndGetsPrimaryHeadline(): void
    {
        $cluster = new StoryCluster();
        $result = $cluster->setPrimaryHeadline('EU announces new sanctions against Russia');

        $this->assertSame('EU announces new sanctions against Russia', $cluster->getPrimaryHeadline());
        $this->assertSame($cluster, $result);
    }

    #[Test]
    public function itSetsAndGetsImportanceScore(): void
    {
        $cluster = new StoryCluster();
        $cluster->setImportanceScore(0.85);

        $this->assertSame(0.85, $cluster->getImportanceScore());
    }

    #[Test]
    public function itSetsAndGetsStatus(): void
    {
        $cluster = new StoryCluster();
        $cluster->setStatus(StoryClusterStatus::APPROVED);

        $this->assertSame(StoryClusterStatus::APPROVED, $cluster->getStatus());
    }

    #[Test]
    public function itManagesPressReleases(): void
    {
        $cluster = new StoryCluster();
        $pr1 = new PressRelease();
        $pr2 = new PressRelease();

        $cluster->addPressRelease($pr1);
        $cluster->addPressRelease($pr2);
        $this->assertCount(2, $cluster->getPressReleases());

        // Adding same PR again should not duplicate
        $cluster->addPressRelease($pr1);
        $this->assertCount(2, $cluster->getPressReleases());

        $cluster->removePressRelease($pr1);
        $this->assertCount(1, $cluster->getPressReleases());
    }

    #[Test]
    public function itManagesTopics(): void
    {
        $cluster = new StoryCluster();
        $topic = new Topic();
        $topic->setTitle('Geopolitics');

        $cluster->addTopic($topic);
        $this->assertCount(1, $cluster->getTopics());
        $this->assertTrue($cluster->getTopics()->contains($topic));

        $cluster->removeTopic($topic);
        $this->assertCount(0, $cluster->getTopics());
    }

    #[Test]
    public function itRecalculatesCounts(): void
    {
        $cluster = new StoryCluster();

        $pr1 = new PressRelease();
        $pr1->setSourcePublisherDomain('reuters.com');

        $pr2 = new PressRelease();
        $pr2->setSourcePublisherDomain('bbc.co.uk');

        $pr3 = new PressRelease();
        $pr3->setSourcePublisherDomain('reuters.com'); // same source

        $cluster->addPressRelease($pr1);
        $cluster->addPressRelease($pr2);
        $cluster->addPressRelease($pr3);

        $cluster->recalculateCounts();

        $this->assertSame(3, $cluster->getArticleCount());
        $this->assertSame(2, $cluster->getSourceCount()); // reuters + bbc
    }

    #[Test]
    public function itSetsAndGetsEditorialBoost(): void
    {
        $cluster = new StoryCluster();
        $cluster->setEditorialBoost(2.5);

        $this->assertSame(2.5, $cluster->getEditorialBoost());
    }

    #[Test]
    public function itSetsAndGetsKeyFacts(): void
    {
        $cluster = new StoryCluster();
        $facts = ['EU sanctions target energy sector', '12 countries support the measure'];
        $cluster->setKeyFacts($facts);

        $this->assertSame($facts, $cluster->getKeyFacts());
    }

    #[Test]
    public function itSetsAndGetsRegionTags(): void
    {
        $cluster = new StoryCluster();
        $tags = ['EU', 'RU', 'UA'];
        $cluster->setRegionTags($tags);

        $this->assertSame($tags, $cluster->getRegionTags());
        $this->assertSame($tags, $cluster->getDistinctCountries());
    }

    #[Test]
    public function itSetsAndGetsPromotedToPressRelease(): void
    {
        $cluster = new StoryCluster();
        $this->assertFalse($cluster->isPromotedToPressRelease());

        $cluster->setPromotedToPressRelease(true);
        $this->assertTrue($cluster->isPromotedToPressRelease());
    }

    #[Test]
    public function itSetsAndGetsSummaryFields(): void
    {
        $cluster = new StoryCluster();

        $cluster->setSummaryShort('Short summary');
        $this->assertSame('Short summary', $cluster->getSummaryShort());

        $cluster->setSummaryMedium('Medium-length summary with more detail');
        $this->assertSame('Medium-length summary with more detail', $cluster->getSummaryMedium());

        $cluster->setWhyItMatters('This matters because...');
        $this->assertSame('This matters because...', $cluster->getWhyItMatters());
    }

    #[Test]
    public function itSetsAndGetsTimestamps(): void
    {
        $cluster = new StoryCluster();
        $now = new \DateTimeImmutable('2026-04-07 12:00:00');

        $cluster->setFirstSeenAt($now);
        $this->assertSame($now, $cluster->getFirstSeenAt());

        $later = new \DateTimeImmutable('2026-04-07 14:00:00');
        $cluster->setLastUpdatedAt($later);
        $this->assertSame($later, $cluster->getLastUpdatedAt());
    }
}
