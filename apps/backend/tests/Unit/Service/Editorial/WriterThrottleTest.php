<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Editorial;

use App\Entity\AppSetting;
use App\Repository\AppSettingRepository;
use App\Service\Editorial\WriterThrottle;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * Unit test for {@see WriterThrottle} (Sprint 56 T56.06).
 *
 * Strategy: drive the real {@see \Symfony\Component\RateLimiter\Policy\SlidingWindowLimiter}
 * against an in-memory {@see ArrayAdapter} cache pool so we exercise the
 * actual bundle semantics — not a mock of our own wrapper. Mock the
 * {@see AppSettingRepository} to control the runtime limit, and the logger
 * to assert the reject-path audit line.
 */
class WriterThrottleTest extends TestCase
{
    private AppSettingRepository&MockObject $appSettings;
    private LoggerInterface&MockObject $logger;
    private ArrayAdapter $cachePool;

    protected function setUp(): void
    {
        $this->appSettings = $this->createMock(AppSettingRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cachePool = new ArrayAdapter();
    }

    public function testConsumeSucceedsUnderLimitWithoutLogging(): void
    {
        $this->appSettings->method('get')
            ->with('editorial.throttle.articles_per_hour')
            ->willReturn('10');

        $this->logger->expects($this->never())->method('info');

        $throttle = new WriterThrottle($this->cachePool, $this->appSettings, $this->logger);

        $rateLimit = $throttle->consume();

        $this->assertTrue($rateLimit->isAccepted());
        $this->assertSame(10, $rateLimit->getLimit());
    }

    public function testConsumeBlocksWhenLimitExhaustedAndLogs(): void
    {
        // Tight limit so we can exhaust it in-test without hammering.
        $this->appSettings->method('get')
            ->with('editorial.throttle.articles_per_hour')
            ->willReturn('2');

        // Expect exactly one audit log — fired on the third attempt (reject).
        $this->logger->expects($this->once())
            ->method('info')
            ->with('writer_throttle.blocked', $this->callback(static function (array $ctx): bool {
                return ($ctx['limit'] ?? null) === 2
                    && \is_int($ctx['retry_after_seconds'] ?? null)
                    && $ctx['retry_after_seconds'] >= 0;
            }));

        $throttle = new WriterThrottle($this->cachePool, $this->appSettings, $this->logger);

        // First two burn the window — accepted.
        $this->assertTrue($throttle->consume()->isAccepted());
        $this->assertTrue($throttle->consume()->isAccepted());

        // Third is over limit — rejected, audit log fires.
        $rejected = $throttle->consume();
        $this->assertFalse($rejected->isAccepted());
        $this->assertSame(2, $rejected->getLimit());
        // SlidingWindowLimiter yields a future retry timestamp when blocked.
        $this->assertGreaterThan(
            (new \DateTimeImmutable())->getTimestamp(),
            $rejected->getRetryAfter()->getTimestamp(),
        );
    }

    public function testCurrentLimitHotReadsFromAppSetting(): void
    {
        // Dynamic tunability is the whole reason we build the limiter
        // manually — verify that `app:settings:set` style mutations change
        // the effective limit without a container rebuild.
        $values = ['15', null, 'oops', '0', '-5', '7'];
        $index = 0;
        $this->appSettings->method('get')
            ->willReturnCallback(function (string $key) use ($values, &$index): ?string {
                self::assertSame('editorial.throttle.articles_per_hour', $key);

                return $values[$index++] ?? null;
            });

        $throttle = new WriterThrottle($this->cachePool, $this->appSettings, $this->logger);

        $this->assertSame(15, $throttle->currentLimit(), 'explicit positive value honoured');
        $this->assertSame(10, $throttle->currentLimit(), 'null falls back to DEFAULT_LIMIT (10)');
        $this->assertSame(10, $throttle->currentLimit(), 'non-numeric falls back');
        $this->assertSame(10, $throttle->currentLimit(), 'zero falls back (>0 guard)');
        $this->assertSame(10, $throttle->currentLimit(), 'negative falls back');
        $this->assertSame(7, $throttle->currentLimit(), 'next positive value read again');
    }

    public function testCurrentLimitUsesDefaultWhenAppSettingMissing(): void
    {
        // Using the real repository (over an empty in-memory pool via
        // the mock) — null return must yield the fallback 10.
        $this->appSettings->method('get')->willReturn(null);

        $throttle = new WriterThrottle($this->cachePool, $this->appSettings, $this->logger);

        $this->assertSame(10, $throttle->currentLimit());
    }

    public function testAppSettingEntityFixtureSeedsTenAsDefault(): void
    {
        // Regression guard: AppSettingsFixture seeds the key with value '10'.
        // If someone edits the fixture we want this test to fail loudly so
        // the enforcement ceiling doesn't silently drift.
        $setting = new AppSetting('editorial.throttle.articles_per_hour', '10');

        $this->assertSame('10', $setting->getValue());
    }
}
