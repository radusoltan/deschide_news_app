<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Aggregator;

use App\Dto\Aggregator\AggregatorResult;
use App\Enum\AggregatorSourceType;
use App\Service\Aggregator\TelegramAggregator;
use App\Service\Aggregator\TelegramSessionManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[CoversClass(TelegramAggregator::class)]
class TelegramAggregatorTest extends TestCase
{
    private const CHANNELS = [
        ['name' => 'Test Channel', 'username' => 'test_channel', 'type' => 'media'],
        ['name' => 'Regional Channel', 'username' => 'regional_ch', 'type' => 'regional'],
    ];

    public function testFetchReturnsEmptyWhenDisabled(): void
    {
        $aggregator = $this->createAggregator(enabled: false);

        self::assertSame([], $aggregator->fetch());
    }

    public function testFetchReturnsEmptyWhenNotConfigured(): void
    {
        $sessionManager = $this->createMock(TelegramSessionManager::class);
        $sessionManager->method('isConfigured')->willReturn(false);

        $aggregator = new TelegramAggregator(
            sessionManager: $sessionManager,
            cache: $this->createMockCache(),
            logger: new NullLogger(),
            channels: self::CHANNELS,
            rateLimitSeconds: 0,
            enabled: true,
        );

        self::assertSame([], $aggregator->fetch());
    }

    public function testFetchExtractsUrlsFromEntityUrl(): void
    {
        $historyResponse = [
            'messages' => [
                [
                    'id' => 100,
                    'message' => 'Breaking news! https://example.com/article-1 Read more',
                    'date' => time(),
                    'entities' => [
                        ['_' => 'messageEntityUrl', 'offset' => 15, 'length' => 30],
                    ],
                ],
            ],
        ];

        $aggregator = $this->createAggregator(
            historyResponse: $historyResponse,
            channels: [self::CHANNELS[0]],
        );

        $results = $aggregator->fetch();

        self::assertCount(1, $results);
        self::assertInstanceOf(AggregatorResult::class, $results[0]);
        self::assertSame('https://example.com/article-1', $results[0]->sourceUrl);
        self::assertSame('Test Channel', $results[0]->sourceName);
        self::assertSame(AggregatorSourceType::TELEGRAM, $results[0]->aggregatorSourceType);
        self::assertSame('ro', $results[0]->sourceLanguage);
    }

    public function testFetchExtractsUrlsFromEntityTextUrl(): void
    {
        $historyResponse = [
            'messages' => [
                [
                    'id' => 101,
                    'message' => 'Check this out',
                    'date' => time(),
                    'entities' => [
                        ['_' => 'messageEntityTextUrl', 'url' => 'https://example.com/article-2'],
                    ],
                ],
            ],
        ];

        $aggregator = $this->createAggregator(
            historyResponse: $historyResponse,
            channels: [self::CHANNELS[0]],
        );

        $results = $aggregator->fetch();

        self::assertCount(1, $results);
        self::assertSame('https://example.com/article-2', $results[0]->sourceUrl);
    }

    public function testFetchSkipsMessagesWithoutUrls(): void
    {
        $historyResponse = [
            'messages' => [
                [
                    'id' => 100,
                    'message' => 'Just a text message without any links',
                    'date' => time(),
                    'entities' => [],
                ],
            ],
        ];

        $aggregator = $this->createAggregator(
            historyResponse: $historyResponse,
            channels: [self::CHANNELS[0]],
        );

        self::assertCount(0, $aggregator->fetch());
    }

    public function testFetchSkipsEmptyMessages(): void
    {
        $historyResponse = [
            'messages' => [
                ['id' => 100, 'message' => '', 'date' => time()],
            ],
        ];

        $aggregator = $this->createAggregator(
            historyResponse: $historyResponse,
            channels: [self::CHANNELS[0]],
        );

        self::assertCount(0, $aggregator->fetch());
    }

    public function testRegionalChannelUsesRussianLanguage(): void
    {
        $historyResponse = [
            'messages' => [
                [
                    'id' => 100,
                    'message' => 'Новость: https://example.com/news',
                    'date' => time(),
                    'entities' => [
                        ['_' => 'messageEntityUrl', 'offset' => 9, 'length' => 24],
                    ],
                ],
            ],
        ];

        $aggregator = $this->createAggregator(
            historyResponse: $historyResponse,
            channels: [self::CHANNELS[1]],
        );

        $results = $aggregator->fetch();

        self::assertCount(1, $results);
        self::assertSame('ru', $results[0]->sourceLanguage);
        self::assertSame('Regional Channel', $results[0]->sourceName);
    }

    public function testFetchExtractsUrlsViaRegexFallback(): void
    {
        $historyResponse = [
            'messages' => [
                [
                    'id' => 100,
                    'message' => 'Visit https://example.com/story for details',
                    'date' => time(),
                    // No entities — regex fallback
                ],
            ],
        ];

        $aggregator = $this->createAggregator(
            historyResponse: $historyResponse,
            channels: [self::CHANNELS[0]],
        );

        $results = $aggregator->fetch();

        self::assertCount(1, $results);
        self::assertSame('https://example.com/story', $results[0]->sourceUrl);
    }

    public function testGetSourceType(): void
    {
        $aggregator = $this->createAggregator(enabled: false);

        self::assertSame(AggregatorSourceType::TELEGRAM, $aggregator->getSourceType());
    }

    public function testGetName(): void
    {
        $aggregator = $this->createAggregator(enabled: false);

        self::assertSame('Telegram', $aggregator->getName());
    }

    public function testFetchHandlesChannelError(): void
    {
        $sessionManager = $this->createMock(TelegramSessionManager::class);
        $sessionManager->method('isConfigured')->willReturn(true);
        $sessionManager->method('getChannelHistory')
            ->willThrowException(new \RuntimeException('Connection failed'));

        $aggregator = new TelegramAggregator(
            sessionManager: $sessionManager,
            cache: $this->createMockCache(),
            logger: new NullLogger(),
            channels: [self::CHANNELS[0]],
            rateLimitSeconds: 0,
            enabled: true,
        );

        // Should not throw — graceful error handling
        $results = $aggregator->fetch();
        self::assertSame([], $results);
    }

    public function testSummaryIsTruncatedTo500Chars(): void
    {
        $longText = 'https://example.com/test ' . str_repeat('A', 600);

        $historyResponse = [
            'messages' => [
                [
                    'id' => 100,
                    'message' => $longText,
                    'date' => time(),
                    'entities' => [
                        ['_' => 'messageEntityUrl', 'offset' => 0, 'length' => 24],
                    ],
                ],
            ],
        ];

        $aggregator = $this->createAggregator(
            historyResponse: $historyResponse,
            channels: [self::CHANNELS[0]],
        );

        $results = $aggregator->fetch();

        self::assertCount(1, $results);
        self::assertLessThanOrEqual(500, mb_strlen($results[0]->summary));
    }

    private function createAggregator(
        bool $enabled = true,
        ?array $historyResponse = null,
        ?array $channels = null,
    ): TelegramAggregator {
        $sessionManager = $this->createMock(TelegramSessionManager::class);
        $sessionManager->method('isConfigured')->willReturn(true);

        if ($historyResponse !== null) {
            $sessionManager->method('getChannelHistory')->willReturn($historyResponse);
        }

        return new TelegramAggregator(
            sessionManager: $sessionManager,
            cache: $this->createMockCache(),
            logger: new NullLogger(),
            channels: $channels ?? self::CHANNELS,
            rateLimitSeconds: 0,
            enabled: $enabled,
        );
    }

    private function createMockCache(): CacheInterface
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('get')->willReturnCallback(
            function (string $key, callable $callback): mixed {
                $item = $this->createMock(ItemInterface::class);
                $item->method('expiresAfter')->willReturnSelf();

                return $callback($item);
            },
        );
        $cache->method('delete')->willReturn(true);

        return $cache;
    }
}
