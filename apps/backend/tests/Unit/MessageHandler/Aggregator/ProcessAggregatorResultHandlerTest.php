<?php

declare(strict_types=1);

namespace App\Tests\Unit\MessageHandler\Aggregator;

use App\Entity\PressRelease;
use App\Enum\DeduplicationResult;
use App\Message\Aggregator\ProcessAggregatorResultMessage;
use App\MessageHandler\Aggregator\ProcessAggregatorResultHandler;
use App\Service\Aggregator\AggregatorStatsCollector;
use App\Service\Aggregator\GoogleNewsUrlResolver;
use App\Service\Aggregator\PressReleaseAggregatorFactory;
use App\Service\Aggregator\SemanticDeduplicatorService;
use App\Repository\SourceRepository;
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

        $urlResolver = $this->createMock(GoogleNewsUrlResolver::class);

        $handler = new ProcessAggregatorResultHandler($dedup, $factory, $translation, $urlResolver, $this->createMock(SourceRepository::class), $em, new NullLogger(), $statsCollector);
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

        $urlResolver = $this->createMock(GoogleNewsUrlResolver::class);

        $handler = new ProcessAggregatorResultHandler($dedup, $factory, $translation, $urlResolver, $this->createMock(SourceRepository::class), $em, new NullLogger(), $statsCollector);
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

        $urlResolver = $this->createMock(GoogleNewsUrlResolver::class);

        $handler = new ProcessAggregatorResultHandler($dedup, $factory, $translation, $urlResolver, $this->createMock(SourceRepository::class), $em, new NullLogger(), $statsCollector);
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

        $urlResolver = $this->createMock(GoogleNewsUrlResolver::class);

        $handler = new ProcessAggregatorResultHandler($dedup, $factory, $translation, $urlResolver, $this->createMock(SourceRepository::class), $em, new NullLogger(), $statsCollector);
        $handler($this->createMessage());
    }

    public function testResolvesGoogleNewsUrl(): void
    {
        $dedup = $this->createMock(SemanticDeduplicatorService::class);
        $dedup->method('evaluate')->willReturn(DeduplicationResult::UNIQUE);

        $pr = new PressRelease();
        $pr->setTitle('Test')->setContent('Content')->setCategorySlug('externe');
        $pr->setSourceUrl('https://news.google.com/rss/articles/CBMi123');

        $factory = $this->createMock(PressReleaseAggregatorFactory::class);
        $factory->method('createFromAggregatorResult')->willReturn($pr);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');

        $translation = $this->createMock(AggregatorTranslationService::class);

        $statsCollector = $this->createMock(AggregatorStatsCollector::class);

        $urlResolver = $this->createMock(GoogleNewsUrlResolver::class);
        $urlResolver->expects(self::once())
            ->method('resolveUrl')
            ->with('https://news.google.com/rss/articles/CBMi123')
            ->willReturn('https://reuters.com/real-article');

        $handler = new ProcessAggregatorResultHandler($dedup, $factory, $translation, $urlResolver, $this->createMock(SourceRepository::class), $em, new NullLogger(), $statsCollector);
        $handler($this->createMessage());

        self::assertSame('https://reuters.com/real-article', $pr->getSourceUrl());
    }

    public function testSkipsUrlResolutionForNonGoogleSources(): void
    {
        $dedup = $this->createMock(SemanticDeduplicatorService::class);
        $dedup->method('evaluate')->willReturn(DeduplicationResult::UNIQUE);

        $pr = new PressRelease();
        $pr->setTitle('Test')->setContent('Content')->setCategorySlug('externe');

        $factory = $this->createMock(PressReleaseAggregatorFactory::class);
        $factory->method('createFromAggregatorResult')->willReturn($pr);

        $em = $this->createMock(EntityManagerInterface::class);
        $translation = $this->createMock(AggregatorTranslationService::class);
        $statsCollector = $this->createMock(AggregatorStatsCollector::class);

        $urlResolver = $this->createMock(GoogleNewsUrlResolver::class);
        $urlResolver->expects(self::never())->method('resolveUrl');

        $handler = new ProcessAggregatorResultHandler($dedup, $factory, $translation, $urlResolver, $this->createMock(SourceRepository::class), $em, new NullLogger(), $statsCollector);
        $handler($this->createMessage(sourceLanguage: 'en', sourceName: 'Bing News'));
    }

    private function createMessage(string $sourceLanguage = 'en', string $sourceName = 'Google News'): ProcessAggregatorResultMessage
    {
        return new ProcessAggregatorResultMessage(
            title: 'Moldova news article',
            summary: 'Summary of the article',
            sourceUrl: 'https://example.com/article/1',
            sourceLanguage: $sourceLanguage,
            sourceName: $sourceName,
            publishedAt: '2026-04-06T10:00:00+00:00',
            rawContent: '<p>Full article content</p>',
            keywords: ['moldova'],
            aggregatorSourceType: 'google_news_rss',
        );
    }
}
