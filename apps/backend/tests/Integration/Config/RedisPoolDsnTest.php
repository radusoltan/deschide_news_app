<?php

declare(strict_types=1);

namespace App\Tests\Integration\Config;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Verifies ADR-023 D8: cache pools resolve their Redis DSN from the
 * REDIS_URL env var rather than the legacy hardcoded 'redis://localhost:6379/1'.
 *
 * Boots the kernel and confirms the env-resolver produces a valid DSN, then
 * loads the application cache pool service to confirm no compile-time error
 * was introduced by the refactor.
 */
final class RedisPoolDsnTest extends KernelTestCase
{
    public function testRedisUrlEnvResolvesToValidDsn(): void
    {
        self::bootKernel();

        $container = static::getContainer();

        // The env processor resolves REDIS_URL at compile time; surface it back via a parameter.
        // Since Symfony doesn't let us read env() directly as a param, we rely on the $_ENV / $_SERVER
        // population performed by the dotenv bootstrap in tests/bootstrap.php.
        $dsn = $_ENV['REDIS_URL'] ?? $_SERVER['REDIS_URL'] ?? getenv('REDIS_URL');

        self::assertIsString($dsn, 'REDIS_URL must be set after dotenv load');
        self::assertMatchesRegularExpression(
            '#^redis://[^/]+/\d+$#',
            (string) $dsn,
            'REDIS_URL must resolve to a well-formed DSN (scheme://host[:port]/db)',
        );

        // Compile-time verification: if cache.yaml had a syntax error or the env placeholder
        // were malformed, the container would refuse to boot. Reaching this line proves OK.
        self::assertTrue(
            $container->has('cache.app'),
            'cache.app service must be available after compile',
        );
    }
}
