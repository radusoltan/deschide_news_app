<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum;

use App\Enum\AggregatorSourceType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AggregatorSourceType::class)]
class AggregatorSourceTypeTest extends TestCase
{
    public function testAllCasesExist(): void
    {
        $cases = AggregatorSourceType::cases();
        self::assertCount(8, $cases);
    }

    #[DataProvider('provideStringValues')]
    public function testFromString(string $value, AggregatorSourceType $expected): void
    {
        self::assertSame($expected, AggregatorSourceType::from($value));
    }

    public static function provideStringValues(): iterable
    {
        yield 'google_news_rss' => ['google_news_rss', AggregatorSourceType::GOOGLE_NEWS_RSS];
        yield 'google_alerts' => ['google_alerts', AggregatorSourceType::GOOGLE_ALERTS];
        yield 'news_api' => ['news_api', AggregatorSourceType::NEWS_API];
        yield 'bing_news' => ['bing_news', AggregatorSourceType::BING_NEWS];
        yield 'telegram' => ['telegram', AggregatorSourceType::TELEGRAM];
        yield 'facebook_rss' => ['facebook_rss', AggregatorSourceType::FACEBOOK_RSS];
        yield 'direct_portal' => ['direct_portal', AggregatorSourceType::DIRECT_PORTAL];
        yield 'gnews' => ['gnews', AggregatorSourceType::GNEWS];
    }

    public function testInvalidStringThrows(): void
    {
        $this->expectException(\ValueError::class);
        AggregatorSourceType::from('invalid_source');
    }

    public function testTryFromReturnsNull(): void
    {
        self::assertNull(AggregatorSourceType::tryFrom('nonexistent'));
    }
}
