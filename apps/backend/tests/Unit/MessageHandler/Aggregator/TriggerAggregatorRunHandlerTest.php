<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler\Aggregator;

use App\Dto\Aggregator\AggregatorResult;
use App\Enum\AggregatorSourceType;
use App\Message\Aggregator\ProcessAggregatorResultMessage;
use App\Message\Aggregator\TriggerAggregatorRunMessage;
use App\MessageHandler\Aggregator\TriggerAggregatorRunHandler;
use App\Service\Aggregator\AggregatorInterface;
use App\Service\Aggregator\AggregatorStatsCollector;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

#[CoversClass(TriggerAggregatorRunHandler::class)]
class TriggerAggregatorRunHandlerTest extends TestCase
{
    public function testDispatchesProcessMessageForEachResult(): void
    {
        $result1 = new AggregatorResult(
            title: 'Article 1',
            summary: 'Summary 1',
            sourceUrl: 'https://example.com/1',
            sourceLanguage: 'en',
            sourceName: 'Google News',
            publishedAt: new \DateTimeImmutable('2026-04-07T10:00:00+00:00'),
            rawContent: '<p>Content 1</p>',
            keywords: ['moldova'],
            aggregatorSourceType: AggregatorSourceType::GOOGLE_NEWS_RSS,
        );

        $result2 = new AggregatorResult(
            title: 'Article 2',
            summary: 'Summary 2',
            sourceUrl: 'https://example.com/2',
            sourceLanguage: 'ro',
            sourceName: 'Google News',
            publishedAt: new \DateTimeImmutable('2026-04-07T11:00:00+00:00'),
            rawContent: '<p>Content 2</p>',
            keywords: ['diaspora'],
            aggregatorSourceType: AggregatorSourceType::GOOGLE_NEWS_RSS,
        );

        $aggregator = $this->createMock(AggregatorInterface::class);
        $aggregator->method('fetch')->willReturn([$result1, $result2]);
        $aggregator->method('getSourceType')->willReturn(AggregatorSourceType::GOOGLE_NEWS_RSS);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::exactly(2))
            ->method('dispatch')
            ->with(self::isInstanceOf(ProcessAggregatorResultMessage::class))
            ->willReturn(new Envelope(new \stdClass()));

        $statsCollector = $this->createMock(AggregatorStatsCollector::class);
        $statsCollector->expects(self::once())->method('invalidateCache');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::atLeastOnce())->method('flush');

        $handler = new TriggerAggregatorRunHandler(
            [$aggregator],
            $bus,
            $statsCollector,
            new NullLogger(),
            $em,
        );

        $handler(new TriggerAggregatorRunMessage(source: null, triggeredBy: 'test'));
    }

    public function testSourceFilteringSkipsNonMatchingAggregators(): void
    {
        $aggregatorGoogle = $this->createMock(AggregatorInterface::class);
        $aggregatorGoogle->method('getSourceType')->willReturn(AggregatorSourceType::GOOGLE_NEWS_RSS);
        $aggregatorGoogle->method('fetch')->willReturn([
            new AggregatorResult(
                title: 'Google Article',
                summary: 'Summary',
                sourceUrl: 'https://example.com/1',
                sourceLanguage: 'en',
                sourceName: 'Google News',
                publishedAt: new \DateTimeImmutable(),
                rawContent: '<p>Content</p>',
                keywords: [],
                aggregatorSourceType: AggregatorSourceType::GOOGLE_NEWS_RSS,
            ),
        ]);

        $aggregatorBing = $this->createMock(AggregatorInterface::class);
        $aggregatorBing->method('getSourceType')->willReturn(AggregatorSourceType::BING_NEWS);
        // fetch() should NOT be called on Bing aggregator
        $aggregatorBing->expects(self::never())->method('fetch');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $statsCollector = $this->createMock(AggregatorStatsCollector::class);
        $statsCollector->expects(self::once())->method('invalidateCache');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::atLeastOnce())->method('flush');

        $handler = new TriggerAggregatorRunHandler(
            [$aggregatorGoogle, $aggregatorBing],
            $bus,
            $statsCollector,
            new NullLogger(),
            $em,
        );

        $handler(new TriggerAggregatorRunMessage(source: 'google_news_rss', triggeredBy: 'test'));
    }

    public function testStatsCacheInvalidatedEvenWhenNoResults(): void
    {
        $aggregator = $this->createMock(AggregatorInterface::class);
        $aggregator->method('getSourceType')->willReturn(AggregatorSourceType::GOOGLE_NEWS_RSS);
        $aggregator->method('fetch')->willReturn([]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::never())->method('dispatch');

        $statsCollector = $this->createMock(AggregatorStatsCollector::class);
        $statsCollector->expects(self::once())->method('invalidateCache');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::atLeastOnce())->method('flush');

        $handler = new TriggerAggregatorRunHandler(
            [$aggregator],
            $bus,
            $statsCollector,
            new NullLogger(),
            $em,
        );

        $handler(new TriggerAggregatorRunMessage(source: null, triggeredBy: 'test'));
    }

    public function testContinuesOnFetchError(): void
    {
        $failingAggregator = $this->createMock(AggregatorInterface::class);
        $failingAggregator->method('getSourceType')->willReturn(AggregatorSourceType::GOOGLE_NEWS_RSS);
        $failingAggregator->method('fetch')->willThrowException(new \RuntimeException('Connection failed'));

        $workingAggregator = $this->createMock(AggregatorInterface::class);
        $workingAggregator->method('getSourceType')->willReturn(AggregatorSourceType::BING_NEWS);
        $workingAggregator->method('fetch')->willReturn([
            new AggregatorResult(
                title: 'Bing Article',
                summary: 'Summary',
                sourceUrl: 'https://example.com/bing',
                sourceLanguage: 'en',
                sourceName: 'Bing News',
                publishedAt: new \DateTimeImmutable(),
                rawContent: '<p>Content</p>',
                keywords: [],
                aggregatorSourceType: AggregatorSourceType::BING_NEWS,
            ),
        ]);

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects(self::once())
            ->method('dispatch')
            ->willReturn(new Envelope(new \stdClass()));

        $statsCollector = $this->createMock(AggregatorStatsCollector::class);
        $statsCollector->expects(self::once())->method('invalidateCache');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist');
        $em->expects(self::atLeastOnce())->method('flush');

        $handler = new TriggerAggregatorRunHandler(
            [$failingAggregator, $workingAggregator],
            $bus,
            $statsCollector,
            new NullLogger(),
            $em,
        );

        // Should NOT throw — the handler gracefully continues past failures
        $handler(new TriggerAggregatorRunMessage(source: null, triggeredBy: 'test'));
    }
}
