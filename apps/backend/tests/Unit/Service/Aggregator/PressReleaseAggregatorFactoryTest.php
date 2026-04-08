<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Aggregator;

use App\Dto\Aggregator\AggregatorResult;
use App\Enum\AggregatorSourceType;
use App\Enum\PressReleaseStatus;
use App\Enum\SourceType;
use App\Service\Aggregator\PressReleaseAggregatorFactory;
use App\Service\CategoryDetectorService;
use App\Service\ContentHasher;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PressReleaseAggregatorFactory::class)]
class PressReleaseAggregatorFactoryTest extends TestCase
{
    public function testCreateFromAggregatorResult(): void
    {
        $catDetector = $this->createMock(CategoryDetectorService::class);
        $catDetector->method('detectSlug')->willReturn('externe');

        $factory = new PressReleaseAggregatorFactory($catDetector, new ContentHasher());

        $publishedAt = new \DateTimeImmutable('2026-04-06T10:00:00+00:00');
        $result = new AggregatorResult(
            title: 'Moldova joins EU framework',
            summary: 'Moldova signed EU agreement.',
            sourceUrl: 'https://reuters.com/article/1',
            sourceLanguage: 'en',
            sourceName: 'Google News',
            publishedAt: $publishedAt,
            rawContent: '<p>Moldova has joined the EU partnership framework.</p>',
            keywords: ['moldova', 'eu'],
            aggregatorSourceType: AggregatorSourceType::GOOGLE_NEWS_RSS,
        );

        $pr = $factory->createFromAggregatorResult($result);

        self::assertSame('Moldova joins EU framework', $pr->getTitle());
        self::assertSame('<p>Moldova has joined the EU partnership framework.</p>', $pr->getContent());
        self::assertSame('Moldova signed EU agreement.', $pr->getLead());
        self::assertSame('https://reuters.com/article/1', $pr->getSourceUrl());
        self::assertSame(SourceType::AGGREGATOR, $pr->getSourceType());
        self::assertSame('aggregator:Google News', $pr->getSourceName());
        self::assertSame('en', $pr->getOriginalLanguage());
        self::assertSame(PressReleaseStatus::PENDING, $pr->getStatus());
        self::assertSame('externe', $pr->getCategorySlug());
        self::assertNotNull($pr->getContentHash());
        self::assertSame($publishedAt, $pr->getReceivedAt());
    }

    public function testSetsSourcePublisherDomain(): void
    {
        $catDetector = $this->createMock(CategoryDetectorService::class);
        $catDetector->method('detectSlug')->willReturn('externe');

        $factory = new PressReleaseAggregatorFactory($catDetector, new ContentHasher());

        $result = new AggregatorResult(
            title: 'Test article',
            summary: 'Summary.',
            sourceUrl: 'https://news.google.com/rss/articles/CBMi123',
            sourceLanguage: 'ro',
            sourceName: 'Moldova 1',
            publishedAt: new \DateTimeImmutable(),
            rawContent: 'Content',
            sourcePublisherDomain: 'moldova1.md',
        );

        $pr = $factory->createFromAggregatorResult($result);

        self::assertSame('moldova1.md', $pr->getSourcePublisherDomain());
        self::assertSame('moldova1.md', $pr->getSourceHostname());
    }

    public function testSourcePublisherDomainNullWhenNotProvided(): void
    {
        $catDetector = $this->createMock(CategoryDetectorService::class);
        $catDetector->method('detectSlug')->willReturn('externe');

        $factory = new PressReleaseAggregatorFactory($catDetector, new ContentHasher());

        $result = new AggregatorResult(
            title: 'Test article',
            summary: '',
            sourceUrl: 'https://example.com/article',
            sourceLanguage: 'en',
            sourceName: 'Test Source',
            publishedAt: new \DateTimeImmutable(),
            rawContent: 'Content',
        );

        $pr = $factory->createFromAggregatorResult($result);

        self::assertNull($pr->getSourcePublisherDomain());
    }

    public function testTitleIsTruncated(): void
    {
        $catDetector = $this->createMock(CategoryDetectorService::class);
        $catDetector->method('detectSlug')->willReturn('societate');

        $factory = new PressReleaseAggregatorFactory($catDetector, new ContentHasher());

        $longTitle = str_repeat('A very long title. ', 30);
        $result = new AggregatorResult(
            title: $longTitle,
            summary: '',
            sourceUrl: 'https://example.com',
            sourceLanguage: 'ro',
            sourceName: 'Test',
            publishedAt: new \DateTimeImmutable(),
            rawContent: 'Content',
        );

        $pr = $factory->createFromAggregatorResult($result);
        self::assertLessThanOrEqual(255, mb_strlen($pr->getTitle()));
    }
}
