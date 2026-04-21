<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial;

use App\Entity\Topic;
use App\Enum\BriefingCadence;
use App\Repository\AppSettingRepository;
use App\Service\Editorial\BriefingEligibilityGateService;
use App\ValueObject\DateRange;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class BriefingEligibilityGateServiceTest extends TestCase
{
    private BriefingEligibilityGateService $gate;
    private AppSettingRepository $settings;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->settings = $this->createMock(AppSettingRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);

        $this->gate = new BriefingEligibilityGateService(
            $this->settings,
            $this->em,
            new NullLogger(),
        );
    }

    public function testGlobalBriefingDisabledBlocksAll(): void
    {
        $this->settings->method('getBool')
            ->willReturnCallback(fn(string $key) => match ($key) {
                'briefing.enabled' => false,
                default => true,
            });

        $topic = $this->createActiveTopic();
        $range = DateRange::lastDay();

        $decision = $this->gate->evaluate($topic, BriefingCadence::DAILY, $range);

        $this->assertFalse($decision->eligible);
        $this->assertContains('briefing_disabled', $decision->reasons);
    }

    public function testCadenceDisabledBlocks(): void
    {
        $this->settings->method('getBool')
            ->willReturnCallback(fn(string $key) => match ($key) {
                'briefing.enabled' => true,
                'briefing.hourly.enabled' => false,
                default => true,
            });

        $topic = $this->createActiveTopic();
        $range = DateRange::lastHour();

        $decision = $this->gate->evaluate($topic, BriefingCadence::HOURLY, $range);

        $this->assertFalse($decision->eligible);
        $this->assertContains('cadence_disabled', $decision->reasons);
    }

    public function testInactiveTopicBlocked(): void
    {
        $this->configureAllEnabled();

        $topic = new Topic();
        $topic->setTitle('Inactive Topic');
        $topic->setIsActive(false);

        $range = DateRange::lastDay();

        $decision = $this->gate->evaluate($topic, BriefingCadence::DAILY, $range);

        $this->assertFalse($decision->eligible);
        $this->assertContains('topic_inactive', $decision->reasons);
    }

    public function testEligibleDailyBriefing(): void
    {
        $this->configureAllEnabled();
        $this->mockPrMetrics(8, 0.9);

        $topic = $this->createActiveTopic();
        $range = DateRange::lastDay();

        $decision = $this->gate->evaluate($topic, BriefingCadence::DAILY, $range);

        $this->assertTrue($decision->eligible);
        $this->assertSame(BriefingCadence::DAILY, $decision->cadence);
        $this->assertSame(8, $decision->prCount);
        $this->assertSame(0.9, $decision->avgRelevance);
        $this->assertSame([], $decision->reasons);
    }

    public function testLowPrCountBlocks(): void
    {
        $this->configureAllEnabled();
        $this->mockPrMetrics(2, 0.95);

        $topic = $this->createActiveTopic();
        $range = DateRange::lastDay();

        $decision = $this->gate->evaluate($topic, BriefingCadence::DAILY, $range);

        $this->assertFalse($decision->eligible);
        $this->assertStringContainsString('low_pr_count', $decision->reasons[0]);
    }

    public function testLowRelevanceBlocks(): void
    {
        $this->configureAllEnabled();
        $this->mockPrMetrics(10, 0.4);

        $topic = $this->createActiveTopic();
        $range = DateRange::lastDay();

        $decision = $this->gate->evaluate($topic, BriefingCadence::DAILY, $range);

        $this->assertFalse($decision->eligible);
        $this->assertStringContainsString('low_relevance', $decision->reasons[0]);
    }

    public function testHourlyCadenceRequiresHigherThresholds(): void
    {
        $this->configureAllEnabled();
        // 3 PRs with 0.85 avg confidence = exactly at hourly threshold
        $this->mockPrMetrics(3, 0.85);

        $topic = $this->createActiveTopic();
        $range = DateRange::lastHour();

        $decision = $this->gate->evaluate($topic, BriefingCadence::HOURLY, $range);

        $this->assertTrue($decision->eligible);
    }

    public function testWeeklyCadencePermissiveThresholds(): void
    {
        $this->configureAllEnabled();
        $this->mockPrMetrics(10, 0.55);

        $topic = $this->createActiveTopic();
        $range = DateRange::lastWeek();

        $decision = $this->gate->evaluate($topic, BriefingCadence::WEEKLY, $range);

        $this->assertTrue($decision->eligible);
    }

    public function testMultipleReasonsAccumulate(): void
    {
        $this->configureAllEnabled();
        // Below both daily thresholds: count < 5, confidence < 0.70
        $this->mockPrMetrics(2, 0.4);

        $topic = $this->createActiveTopic();
        $range = DateRange::lastDay();

        $decision = $this->gate->evaluate($topic, BriefingCadence::DAILY, $range);

        $this->assertFalse($decision->eligible);
        $this->assertCount(2, $decision->reasons);
    }

    public function testDecisionContainsCadence(): void
    {
        $this->configureAllEnabled();
        $this->mockPrMetrics(15, 0.9);

        $topic = $this->createActiveTopic();
        $range = DateRange::lastWeek();

        $decision = $this->gate->evaluate($topic, BriefingCadence::WEEKLY, $range);

        $this->assertSame(BriefingCadence::WEEKLY, $decision->cadence);
    }

    private function createActiveTopic(): Topic
    {
        $topic = new Topic();
        $topic->setTitle('Test Topic');
        $topic->setIsActive(true);

        return $topic;
    }

    private function configureAllEnabled(): void
    {
        $this->settings->method('getBool')
            ->willReturn(true);
        $this->settings->method('getInt')
            ->willReturnCallback(fn(string $key, int $default) => $default);
        $this->settings->method('getFloat')
            ->willReturnCallback(fn(string $key, float $default) => $default);
    }

    private function mockPrMetrics(int $count, float $avgRelevance): void
    {
        $query = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getSingleResult'])
            ->getMock();
        $query->method('getSingleResult')
            ->willReturn(['cnt' => $count, 'avgConf' => $avgRelevance]);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('join')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->em->method('createQueryBuilder')->willReturn($qb);
    }
}
