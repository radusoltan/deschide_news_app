<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Aggregator;

use App\Dto\Aggregator\TrendQuery;
use App\Entity\Topic;
use App\Enum\AggregatorSourceType;
use App\Repository\TopicRepository;
use App\Service\Aggregator\TrendQueryGeneratorService;
use App\Service\Aggregator\TrendScoringService;
use Doctrine\ORM\EntityManagerInterface;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[CoversClass(TrendQueryGeneratorService::class)]
class TrendQueryGeneratorServiceTest extends TestCase
{
    private TrendScoringService&MockObject $scoringService;
    private TopicRepository&MockObject $topicRepo;
    private EntityManagerInterface&MockObject $em;
    private CacheInterface&MockObject $cache;
    private TrendQueryGeneratorService $service;

    protected function setUp(): void
    {
        $this->scoringService = $this->createMock(TrendScoringService::class);
        $this->topicRepo = $this->createMock(TopicRepository::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->cache = $this->createMock(CacheInterface::class);

        $this->service = new TrendQueryGeneratorService(
            $this->scoringService,
            $this->topicRepo,
            $this->em,
            $this->cache,
            new NullLogger(),
        );
    }

    public function testGenerateQueriesReturnsEmptyWhenNoTrending(): void
    {
        $this->scoringService->method('getTopTrendingTopics')->willReturn([]);

        $result = $this->service->generateQueries();

        self::assertSame([], $result);
    }

    public function testGenerateQueriesCreatesQueriesForAllAggregatorTypes(): void
    {
        $this->scoringService->method('getTopTrendingTopics')->willReturn([
            [
                'topicId' => 1,
                'topicName' => 'Transnistria',
                'score' => 5.0,
                'articleCount' => 10,
                'velocity' => 1.43,
            ],
        ]);

        $topic = $this->createTopicMock(1, 'Transnistria');
        $this->topicRepo->method('find')->with(1)->willReturn($topic);
        $this->mockTranslations($topic, [
            'en' => ['title' => 'Transnistria'],
            'ru' => ['title' => 'Приднестровье'],
        ]);

        $queries = $this->service->generateQueries();

        // Should generate 3 queries: Google News RSS, NewsAPI, Bing News
        self::assertCount(3, $queries);

        $types = array_map(fn (TrendQuery $q) => $q->aggregatorType, $queries);
        self::assertContains(AggregatorSourceType::GOOGLE_NEWS_RSS, $types);
        self::assertContains(AggregatorSourceType::NEWS_API, $types);
        self::assertContains(AggregatorSourceType::BING_NEWS, $types);
    }

    public function testGoogleNewsRssUsesUrlEncodedKeyword(): void
    {
        $this->scoringService->method('getTopTrendingTopics')->willReturn([
            ['topicId' => 1, 'topicName' => 'EU Accession', 'score' => 3.0, 'articleCount' => 5, 'velocity' => 1.0],
        ]);

        $topic = $this->createTopicMock(1, 'Aderare UE');
        $this->topicRepo->method('find')->with(1)->willReturn($topic);
        $this->mockTranslations($topic, [
            'en' => ['title' => 'EU Accession'],
            'ru' => ['title' => 'Вступление в ЕС'],
        ]);

        $queries = $this->service->generateQueries();

        $gnQuery = array_values(array_filter($queries, fn (TrendQuery $q) => $q->aggregatorType === AggregatorSourceType::GOOGLE_NEWS_RSS));
        self::assertNotEmpty($gnQuery);
        // RO title should be URL-encoded
        self::assertSame(rawurlencode('Aderare UE'), $gnQuery[0]->formattedQuery);
    }

    public function testNewsApiUsesBooleanOrQuery(): void
    {
        $this->scoringService->method('getTopTrendingTopics')->willReturn([
            ['topicId' => 1, 'topicName' => 'Transnistria', 'score' => 5.0, 'articleCount' => 10, 'velocity' => 1.0],
        ]);

        $topic = $this->createTopicMock(1, 'Transnistria');
        $this->topicRepo->method('find')->with(1)->willReturn($topic);
        $this->mockTranslations($topic, [
            'en' => ['title' => 'Transnistria'],
            'ru' => ['title' => 'Приднестровье'],
        ]);

        $queries = $this->service->generateQueries();

        $naQuery = array_values(array_filter($queries, fn (TrendQuery $q) => $q->aggregatorType === AggregatorSourceType::NEWS_API));
        self::assertNotEmpty($naQuery);
        self::assertStringContainsString(' OR ', $naQuery[0]->formattedQuery);
        self::assertStringContainsString('"Transnistria"', $naQuery[0]->formattedQuery);
        self::assertStringContainsString('"Приднестровье"', $naQuery[0]->formattedQuery);
    }

    public function testCacheQueriesClearsAndStoresInCache(): void
    {
        $queries = [
            new TrendQuery(1, 'Test', ['kw'], 'ro', AggregatorSourceType::GOOGLE_NEWS_RSS, 'test', 1.0),
        ];

        $this->cache->expects(self::exactly(2))->method('delete');
        $this->cache->expects(self::once())->method('get')
            ->willReturnCallback(function (string $key, callable $callback) use ($queries) {
                $item = $this->createMock(ItemInterface::class);
                $item->expects(self::once())->method('expiresAfter')->with(21600);

                return $callback($item);
            });

        $this->service->cacheQueries($queries);
    }

    public function testGetRelevanceKeywordsReturnsUniqueKeywords(): void
    {
        $this->cache->method('get')->willReturnCallback(function (string $key, callable $callback) {
            $item = $this->createMock(ItemInterface::class);
            $item->method('expiresAfter');

            return $callback($item);
        });

        $this->scoringService->method('getTopTrendingTopics')->willReturn([
            ['topicId' => 1, 'topicName' => 'Transnistria', 'score' => 5.0, 'articleCount' => 10, 'velocity' => 1.0],
        ]);

        $topic = $this->createTopicMock(1, 'Transnistria');
        $this->topicRepo->method('find')->with(1)->willReturn($topic);
        $this->mockTranslations($topic, [
            'en' => ['title' => 'Transnistria'],
            'ru' => ['title' => 'Приднестровье'],
        ]);

        $keywords = $this->service->getRelevanceKeywords();

        self::assertContains('Transnistria', $keywords);
        self::assertContains('Приднестровье', $keywords);
        // No duplicates
        self::assertSame(\count($keywords), \count(array_unique($keywords)));
    }

    public function testSkipsTopicNotFoundInRepository(): void
    {
        $this->scoringService->method('getTopTrendingTopics')->willReturn([
            ['topicId' => 999, 'topicName' => 'Deleted Topic', 'score' => 5.0, 'articleCount' => 10, 'velocity' => 1.0],
        ]);

        $this->topicRepo->method('find')->with(999)->willReturn(null);

        $queries = $this->service->generateQueries();

        self::assertSame([], $queries);
    }

    private function createTopicMock(int $id, string $title): Topic&MockObject
    {
        $topic = $this->createMock(Topic::class);
        $topic->method('getId')->willReturn($id);
        $topic->method('getTitle')->willReturn($title);

        return $topic;
    }

    private function mockTranslations(Topic&MockObject $topic, array $translations): void
    {
        $translationRepo = $this->createMock(TranslationRepository::class);
        $translationRepo->method('findTranslations')->with($topic)->willReturn($translations);

        $this->em->method('getRepository')->willReturn($translationRepo);
    }
}
