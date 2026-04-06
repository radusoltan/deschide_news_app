<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto\Aggregator;

use App\Dto\Aggregator\AggregatorResult;
use App\Enum\AggregatorSourceType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(AggregatorResult::class)]
class AggregatorResultTest extends TestCase
{
    public function testAllFieldsAreAccessible(): void
    {
        $publishedAt = new \DateTimeImmutable('2026-04-06T10:00:00+00:00');

        $result = new AggregatorResult(
            title: 'Moldovan citizen wins award',
            summary: 'A Moldovan citizen has won an international award.',
            sourceUrl: 'https://example.com/article/1',
            sourceLanguage: 'en',
            sourceName: 'Google News',
            publishedAt: $publishedAt,
            rawContent: '<p>Full article content here.</p>',
            keywords: ['moldova', 'award'],
            aggregatorSourceType: AggregatorSourceType::GOOGLE_NEWS_RSS,
        );

        self::assertSame('Moldovan citizen wins award', $result->title);
        self::assertSame('A Moldovan citizen has won an international award.', $result->summary);
        self::assertSame('https://example.com/article/1', $result->sourceUrl);
        self::assertSame('en', $result->sourceLanguage);
        self::assertSame('Google News', $result->sourceName);
        self::assertSame($publishedAt, $result->publishedAt);
        self::assertSame('<p>Full article content here.</p>', $result->rawContent);
        self::assertSame(['moldova', 'award'], $result->keywords);
        self::assertSame(AggregatorSourceType::GOOGLE_NEWS_RSS, $result->aggregatorSourceType);
    }

    public function testDefaultValues(): void
    {
        $result = new AggregatorResult(
            title: 'Test',
            summary: 'Test summary',
            sourceUrl: 'https://example.com',
            sourceLanguage: 'ro',
            sourceName: 'Test Source',
            publishedAt: new \DateTimeImmutable(),
            rawContent: 'content',
        );

        self::assertSame([], $result->keywords);
        self::assertNull($result->aggregatorSourceType);
    }

    public function testReadonlyProperties(): void
    {
        $result = new AggregatorResult(
            title: 'Test',
            summary: 'Summary',
            sourceUrl: 'https://example.com',
            sourceLanguage: 'ro',
            sourceName: 'Source',
            publishedAt: new \DateTimeImmutable(),
            rawContent: 'content',
        );

        $reflection = new \ReflectionClass($result);
        self::assertTrue($reflection->isReadOnly());
    }
}
