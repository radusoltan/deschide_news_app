<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler\Aggregator;

use App\Entity\PressRelease;
use App\Enum\DeduplicationResult;
use App\Message\Aggregator\ProcessAggregatorResultMessage;
use App\MessageHandler\Aggregator\ProcessAggregatorResultHandler;
use App\Service\Aggregator\AggregatorStatsCollector;
use App\Service\Aggregator\PressReleaseAggregatorFactory;
use App\Service\Aggregator\SemanticDeduplicatorService;
use App\Service\Translation\AggregatorTranslationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(ProcessAggregatorResultHandler::class)]
class ProcessAggregatorResultHandlerTest extends TestCase
{
    public function testSkipsDuplicate(): void
    {
        $dedup = $this->createMock(SemanticDeduplicatorService::class);
        $dedup->method('evaluate')->willReturn(DeduplicationResult::DUPLICATE);

        $factory = $this->createMock(PressReleaseAggregatorFactory::class);
        $factory->expects(self::never())->method('createFromAggregatorResult');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $translation = $this->createMock(AggregatorTranslationService::class);

        $statsCollector = $this->createMock(AggregatorStatsCollector::class);
        $statsCollector->expects(self::never())->method('invalidateCache');

        $handler = new ProcessAggregatorResultHandler($dedup, $factory, $translation, $em, new NullLogger(), $statsCollector);
        $handler($this->createMessage());
    }

    public function testPersistsUniqueResult(): void
    {
        $dedup = $this->createMock(SemanticDeduplicatorService::class);
        $dedup->method('evaluate')->willReturn(DeduplicationResult::UNIQUE);

        $pr = new PressRelease();
        $pr->setTitle('Test')->setContent('Content')->setCategorySlug('societate');

        $factory = $this->createMock(PressReleaseAggregatorFactory::class);
        $factory->method('createFromAggregatorResult')->willReturn($pr);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with($pr);
        $em->expects(self::once())->method('flush');

        $translation = $this->createMock(AggregatorTranslationService::class);
        $translation->expects(self::once())->method('translateToRomanian');

        $statsCollector = $this->createMock(AggregatorStatsCollector::class);
        $statsCollector->expects(self::once())->method('invalidateCache');

        $handler = new ProcessAggregatorResultHandler($dedup, $factory, $translation, $em, new NullLogger(), $statsCollector);
        $handler($this->createMessage(sourceLanguage: 'en'));
    }

    public function testSkipsTranslationForRomanian(): void
    {
        $dedup = $this->createMock(SemanticDeduplicatorService::class);
        $dedup->method('evaluate')->willReturn(DeduplicationResult::UNIQUE);

        $pr = new PressRelease();
        $pr->setTitle('Test')->setContent('Content')->setCategorySlug('societate');

        $factory = $this->createMock(PressReleaseAggregatorFactory::class);
        $factory->method('createFromAggregatorResult')->willReturn($pr);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $translation = $this->createMock(AggregatorTranslationService::class);
        $translation->expects(self::never())->method('translateToRomanian');

        $statsCollector = $this->createMock(AggregatorStatsCollector::class);
        $statsCollector->expects(self::once())->method('invalidateCache');

        $handler = new ProcessAggregatorResultHandler($dedup, $factory, $translation, $em, new NullLogger(), $statsCollector);
        $handler($this->createMessage(sourceLanguage: 'ro'));
    }

    public function testPersistsNeedsReviewResult(): void
    {
        $dedup = $this->createMock(SemanticDeduplicatorService::class);
        $dedup->method('evaluate')->willReturn(DeduplicationResult::NEEDS_REVIEW);

        $pr = new PressRelease();
        $pr->setTitle('Test')->setContent('Content')->setCategorySlug('societate');

        $factory = $this->createMock(PressReleaseAggregatorFactory::class);
        $factory->method('createFromAggregatorResult')->willReturn($pr);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $translation = $this->createMock(AggregatorTranslationService::class);

        $statsCollector = $this->createMock(AggregatorStatsCollector::class);
        $statsCollector->expects(self::once())->method('invalidateCache');

        $handler = new ProcessAggregatorResultHandler($dedup, $factory, $translation, $em, new NullLogger(), $statsCollector);
        $handler($this->createMessage());
    }

    private function createMessage(string $sourceLanguage = 'en'): ProcessAggregatorResultMessage
    {
        return new ProcessAggregatorResultMessage(
            title: 'Moldova news article',
            summary: 'Summary of the article',
            sourceUrl: 'https://example.com/article/1',
            sourceLanguage: $sourceLanguage,
            sourceName: 'Google News',
            publishedAt: '2026-04-06T10:00:00+00:00',
            rawContent: '<p>Full article content</p>',
            keywords: ['moldova'],
            aggregatorSourceType: 'google_news_rss',
        );
    }
}
