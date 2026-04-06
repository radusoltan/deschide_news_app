<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Aggregator;

use App\Repository\TopicRepository;
use App\Service\Aggregator\TrendScoringService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(TrendScoringService::class)]
class TrendScoringServiceTest extends TestCase
{
    private EntityManagerInterface&MockObject $em;
    private TopicRepository&MockObject $topicRepo;
    private TrendScoringService $service;

    protected function setUp(): void
    {
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->topicRepo = $this->createMock(TopicRepository::class);

        $this->service = new TrendScoringService(
            $this->topicRepo,
            $this->em,
            new NullLogger(),
        );
    }

    public function testEmptyResultsReturnsEmptyArray(): void
    {
        $this->mockQueryResults([]);

        $result = $this->service->getTopTrendingTopics(days: 7, limit: 20);

        self::assertSame([], $result);
    }

    public function testSingleTopicWithOneArticle(): void
    {
        $this->mockQueryResults([
            [
                'topicId' => 1,
                'topicName' => 'Transnistria',
                'articleId' => 100,
                'createdAt' => new \DateTimeImmutable('-2 hours'),
                'sourceEmail' => null,
            ],
        ]);

        $result = $this->service->getTopTrendingTopics(days: 7, limit: 20);

        self::assertCount(1, $result);
        self::assertSame(1, $result[0]['topicId']);
        self::assertSame('Transnistria', $result[0]['topicName']);
        self::assertSame(1, $result[0]['articleCount']);
        self::assertGreaterThan(0, $result[0]['score']);
    }

    public function testMultipleTopicsSortedByScore(): void
    {
        $this->mockQueryResults([
            [
                'topicId' => 1,
                'topicName' => 'EU Accession',
                'articleId' => 100,
                'createdAt' => new \DateTimeImmutable('-1 hour'),
                'sourceEmail' => null,
            ],
            [
                'topicId' => 2,
                'topicName' => 'Energy Security',
                'articleId' => 200,
                'createdAt' => new \DateTimeImmutable('-5 days'),
                'sourceEmail' => null,
            ],
        ]);

        $result = $this->service->getTopTrendingTopics(days: 7, limit: 20);

        self::assertCount(2, $result);
        self::assertSame(1, $result[0]['topicId']);
        self::assertSame(2, $result[1]['topicId']);
        self::assertGreaterThan($result[1]['score'], $result[0]['score']);
    }

    public function testTemporalDecayReducesScoreOverTime(): void
    {
        // Recent article (1 hour old)
        $result1 = $this->createServiceWithResults([
            [
                'topicId' => 1,
                'topicName' => 'Topic A',
                'articleId' => 100,
                'createdAt' => new \DateTimeImmutable('-1 hour'),
                'sourceEmail' => null,
            ],
        ])->getTopTrendingTopics(days: 7, limit: 20);

        // Old article (48 hours old)
        $result2 = $this->createServiceWithResults([
            [
                'topicId' => 1,
                'topicName' => 'Topic A',
                'articleId' => 100,
                'createdAt' => new \DateTimeImmutable('-48 hours'),
                'sourceEmail' => null,
            ],
        ])->getTopTrendingTopics(days: 7, limit: 20);

        self::assertGreaterThan($result2[0]['score'], $result1[0]['score']);
    }

    public function testSourceWeightAffectsScore(): void
    {
        // Reuters article (weight 3.0)
        $resultHighWeight = $this->createServiceWithResults([
            [
                'topicId' => 1,
                'topicName' => 'High Weight',
                'articleId' => 100,
                'createdAt' => new \DateTimeImmutable('-2 hours'),
                'sourceEmail' => 'Reuters wire',
            ],
        ])->getTopTrendingTopics(days: 7, limit: 20);

        // Unknown source (weight 0.5)
        $resultLowWeight = $this->createServiceWithResults([
            [
                'topicId' => 2,
                'topicName' => 'Low Weight',
                'articleId' => 200,
                'createdAt' => new \DateTimeImmutable('-2 hours'),
                'sourceEmail' => 'random-blog.com',
            ],
        ])->getTopTrendingTopics(days: 7, limit: 20);

        self::assertGreaterThan($resultLowWeight[0]['score'], $resultHighWeight[0]['score']);
    }

    public function testLimitRespectsMaxResults(): void
    {
        $rows = [];
        for ($i = 1; $i <= 10; $i++) {
            $rows[] = [
                'topicId' => $i,
                'topicName' => "Topic $i",
                'articleId' => $i * 100,
                'createdAt' => new \DateTimeImmutable(sprintf('-%d hours', $i)),
                'sourceEmail' => null,
            ];
        }

        $this->mockQueryResults($rows);

        $result = $this->service->getTopTrendingTopics(days: 7, limit: 5);

        self::assertCount(5, $result);
    }

    public function testVelocityCalculation(): void
    {
        $this->mockQueryResults([
            [
                'topicId' => 1,
                'topicName' => 'Active Topic',
                'articleId' => 100,
                'createdAt' => new \DateTimeImmutable('-1 day'),
                'sourceEmail' => null,
            ],
            [
                'topicId' => 1,
                'topicName' => 'Active Topic',
                'articleId' => 101,
                'createdAt' => new \DateTimeImmutable('-2 days'),
                'sourceEmail' => null,
            ],
            [
                'topicId' => 1,
                'topicName' => 'Active Topic',
                'articleId' => 102,
                'createdAt' => new \DateTimeImmutable('-4 days'),
                'sourceEmail' => null,
            ],
        ]);

        $result = $this->service->getTopTrendingTopics(days: 7, limit: 20);

        self::assertCount(1, $result);
        self::assertSame(3, $result[0]['articleCount']);
        self::assertGreaterThan(0, $result[0]['velocity']);
    }

    private function createServiceWithResults(array $rows): TrendScoringService
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $this->buildQueryMock($em, $rows);

        return new TrendScoringService(
            $this->topicRepo,
            $em,
            new NullLogger(),
        );
    }

    private function mockQueryResults(array $rows): void
    {
        $this->buildQueryMock($this->em, $rows);
    }

    private function buildQueryMock(EntityManagerInterface&MockObject $em, array $rows): void
    {
        $query = $this->createMock(Query::class);
        $query->method('getArrayResult')->willReturn($rows);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('innerJoin')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('addOrderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em->method('createQueryBuilder')->willReturn($qb);
    }
}
