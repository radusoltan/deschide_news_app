<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Clustering;

use App\Entity\PressRelease;
use App\Entity\Source;
use App\Entity\StoryCluster;
use App\Entity\Topic;
use App\Repository\SourceRepository;
use App\Service\Clustering\ImportanceScoreCalculator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ImportanceScoreCalculatorTest extends TestCase
{
    private SourceRepository&MockObject $sourceRepo;
    private ImportanceScoreCalculator $calculator;

    protected function setUp(): void
    {
        $this->sourceRepo = $this->createMock(SourceRepository::class);
        $this->calculator = new ImportanceScoreCalculator(
            $this->sourceRepo,
            new NullLogger(),
        );
    }

    #[Test]
    public function emptyClusterScoresAboveZero(): void
    {
        $cluster = new StoryCluster();
        $cluster->setPrimaryHeadline('Test');
        $cluster->setFirstSeenAt(new \DateTimeImmutable());

        $score = $this->calculator->calculate($cluster);

        // Should still have recency (1.0) and topic criticality (0.5 default)
        $this->assertGreaterThan(0.0, $score);
    }

    #[Test]
    public function multiSourceClusterScoresHigher(): void
    {
        $this->sourceRepo->method('getCredibilityWeight')->willReturn(0.9);
        $this->sourceRepo->method('findByDomain')->willReturn(null);

        $singleSourceCluster = $this->createClusterWithSources(['reuters.com']);
        $multiSourceCluster = $this->createClusterWithSources([
            'reuters.com', 'bbc.co.uk', 'nytimes.com', 'ft.com',
        ]);

        $singleScore = $this->calculator->calculate($singleSourceCluster);
        $multiScore = $this->calculator->calculate($multiSourceCluster);

        $this->assertGreaterThan($singleScore, $multiScore);
    }

    #[Test]
    public function recencyDecaysOverTime(): void
    {
        $this->sourceRepo->method('getCredibilityWeight')->willReturn(0.5);
        $this->sourceRepo->method('findByDomain')->willReturn(null);

        $freshCluster = $this->createClusterWithSources(['reuters.com']);
        $freshCluster->setFirstSeenAt(new \DateTimeImmutable('-1 hour'));

        $oldCluster = $this->createClusterWithSources(['reuters.com']);
        $oldCluster->setFirstSeenAt(new \DateTimeImmutable('-24 hours'));

        $freshScore = $this->calculator->calculate($freshCluster);
        $oldScore = $this->calculator->calculate($oldCluster);

        $this->assertGreaterThan($oldScore, $freshScore);
    }

    #[Test]
    public function editorialBoostMultipliesScore(): void
    {
        $this->sourceRepo->method('getCredibilityWeight')->willReturn(0.5);
        $this->sourceRepo->method('findByDomain')->willReturn(null);

        $normalCluster = $this->createClusterWithSources(['reuters.com']);
        $normalCluster->setEditorialBoost(1.0);

        $boostedCluster = $this->createClusterWithSources(['reuters.com']);
        $boostedCluster->setEditorialBoost(2.0);

        $normalScore = $this->calculator->calculate($normalCluster);
        $boostedScore = $this->calculator->calculate($boostedCluster);

        $this->assertEqualsWithDelta($normalScore * 2.0, $boostedScore, 0.001);
    }

    #[Test]
    public function topicWeightAffectsScore(): void
    {
        $this->sourceRepo->method('getCredibilityWeight')->willReturn(0.5);
        $this->sourceRepo->method('findByDomain')->willReturn(null);

        $lowTopicCluster = $this->createClusterWithSources(['reuters.com']);
        $lowTopic = new Topic();
        $lowTopic->setTitle('Entertainment');
        $lowTopic->setWeight(0.2);
        $lowTopicCluster->addTopic($lowTopic);

        $highTopicCluster = $this->createClusterWithSources(['reuters.com']);
        $highTopic = new Topic();
        $highTopic->setTitle('Security');
        $highTopic->setWeight(0.9);
        $highTopicCluster->addTopic($highTopic);

        $lowScore = $this->calculator->calculate($lowTopicCluster);
        $highScore = $this->calculator->calculate($highTopicCluster);

        $this->assertGreaterThan($lowScore, $highScore);
    }

    #[Test]
    public function scoreIsNormalizedBetweenZeroAndOne(): void
    {
        $this->sourceRepo->method('getCredibilityWeight')->willReturn(0.5);
        $this->sourceRepo->method('findByDomain')->willReturn(null);

        $cluster = $this->createClusterWithSources(['reuters.com']);
        $cluster->setEditorialBoost(1.0);

        $score = $this->calculator->calculate($cluster);

        $this->assertGreaterThanOrEqual(0.0, $score);
        $this->assertLessThanOrEqual(1.0, $score);
    }

    #[Test]
    public function geoDiversityIncreasesWithMultipleCountries(): void
    {
        $sourceGB = new Source();
        $sourceGB->setName('BBC')->setCountry('GB');
        $sourceUS = new Source();
        $sourceUS->setName('NYT')->setCountry('US');
        $sourceFR = new Source();
        $sourceFR->setName('AFP')->setCountry('FR');

        $this->sourceRepo->method('getCredibilityWeight')->willReturn(0.9);
        $this->sourceRepo->method('findByDomain')->willReturnCallback(
            fn (?string $domain) => match ($domain) {
                'bbc.co.uk' => $sourceGB,
                'nytimes.com' => $sourceUS,
                'france24.com' => $sourceFR,
                default => null,
            },
        );

        $singleCountry = $this->createClusterWithSources(['bbc.co.uk']);
        $multiCountry = $this->createClusterWithSources(['bbc.co.uk', 'nytimes.com', 'france24.com']);

        $singleScore = $this->calculator->calculate($singleCountry);
        $multiScore = $this->calculator->calculate($multiCountry);

        $this->assertGreaterThan($singleScore, $multiScore);
    }

    /**
     * @param list<string> $domains
     */
    private function createClusterWithSources(array $domains): StoryCluster
    {
        $cluster = new StoryCluster();
        $cluster->setPrimaryHeadline('Test cluster');
        $cluster->setFirstSeenAt(new \DateTimeImmutable());

        foreach ($domains as $i => $domain) {
            $pr = new PressRelease();
            $pr->setTitle('Article ' . ($i + 1));
            $pr->setContent('Content for article ' . ($i + 1));
            $pr->setCategorySlug('extern');
            $pr->setSourcePublisherDomain($domain);

            $ref = new \ReflectionProperty(PressRelease::class, 'id');
            $ref->setValue($pr, $i + 1);

            $cluster->addPressRelease($pr);
        }

        $cluster->recalculateCounts();

        return $cluster;
    }
}
