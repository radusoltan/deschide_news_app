<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Aggregator;

use App\Service\Aggregator\TelegramSessionManager;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

#[CoversClass(TelegramSessionManager::class)]
class TelegramSessionManagerTest extends TestCase
{
    public function testIsConfiguredReturnsTrueWhenCredentialsProvided(): void
    {
        $manager = new TelegramSessionManager(
            apiId: '12345',
            apiHash: 'abcdef0123456789',
            sessionPath: '/tmp/test_session.madeline',
            logger: new NullLogger(),
        );

        self::assertTrue($manager->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWhenApiIdEmpty(): void
    {
        $manager = new TelegramSessionManager(
            apiId: '',
            apiHash: 'abcdef0123456789',
            sessionPath: '/tmp/test_session.madeline',
            logger: new NullLogger(),
        );

        self::assertFalse($manager->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWhenApiHashEmpty(): void
    {
        $manager = new TelegramSessionManager(
            apiId: '12345',
            apiHash: '',
            sessionPath: '/tmp/test_session.madeline',
            logger: new NullLogger(),
        );

        self::assertFalse($manager->isConfigured());
    }

    public function testIsConfiguredReturnsFalseWhenBothEmpty(): void
    {
        $manager = new TelegramSessionManager(
            apiId: '',
            apiHash: '',
            sessionPath: '/tmp/test_session.madeline',
            logger: new NullLogger(),
        );

        self::assertFalse($manager->isConfigured());
    }
}
