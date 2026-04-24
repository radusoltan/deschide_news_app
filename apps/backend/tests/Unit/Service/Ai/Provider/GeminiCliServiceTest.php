<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Ai\Provider;

use App\Service\Ai\Provider\GeminiCliService;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

class GeminiCliServiceTest extends TestCase
{
    public function testSessionStatsStartAtZero(): void
    {
        $service = new GeminiCliService('/usr/bin/true', sys_get_temp_dir(), new NullLogger());
        $stats = $service->getSessionStats();

        self::assertSame(0, $stats['total_calls']);
        self::assertSame(0, $stats['total_input_tokens']);
        self::assertSame(0, $stats['total_output_tokens']);
        self::assertSame(0.0, $stats['total_cost_usd']);
    }

    /**
     * Verifies that the private cost calculator produces the advertised
     * Gemini Flash price: $0.075 / 1M input + $0.30 / 1M output.
     */
    public function testCalculateCostForFlash(): void
    {
        $service = new GeminiCliService('/usr/bin/true', sys_get_temp_dir(), new NullLogger());
        $ref = new \ReflectionMethod(GeminiCliService::class, 'calculateCost');
        // 1M in + 1M out → $0.075 + $0.30 = $0.375
        $cost = $ref->invoke($service, 'gemini-2.5-flash', 1_000_000, 1_000_000);
        self::assertEqualsWithDelta(0.375, $cost, 0.0001);

        // Unknown model yields zero — keeps the session honest if Gemini
        // introduces new SKUs before we update the table.
        $unknown = $ref->invoke($service, 'gemini-unknown-xl', 1_000_000, 1_000_000);
        self::assertSame(0.0, $unknown);
    }

    public function testCalculateCostForPro(): void
    {
        $service = new GeminiCliService('/usr/bin/true', sys_get_temp_dir(), new NullLogger());
        $ref = new \ReflectionMethod(GeminiCliService::class, 'calculateCost');
        // 1M in + 1M out → $1.25 + $5.00 = $6.25
        $cost = $ref->invoke($service, 'gemini-2.5-pro', 1_000_000, 1_000_000);
        self::assertEqualsWithDelta(6.25, $cost, 0.0001);
    }

    public function testExtractTokenCountsFromEnvelopeMetadata(): void
    {
        $service = new GeminiCliService('/usr/bin/true', sys_get_temp_dir(), new NullLogger());
        $ref = new \ReflectionMethod(GeminiCliService::class, 'extractTokenCounts');
        $envelope = json_encode([
            'response' => [
                'text' => 'ok',
                'usageMetadata' => [
                    'promptTokenCount' => 400,
                    'candidatesTokenCount' => 120,
                ],
            ],
        ], \JSON_THROW_ON_ERROR);

        [$input, $output] = $ref->invoke($service, $envelope, 999);

        self::assertSame(400, $input);
        self::assertSame(120, $output);
    }

    public function testExtractTokenCountsFallsBackToCharHeuristic(): void
    {
        $service = new GeminiCliService('/usr/bin/true', sys_get_temp_dir(), new NullLogger());
        $ref = new \ReflectionMethod(GeminiCliService::class, 'extractTokenCounts');
        // No JSON envelope → heuristic kicks in: ceil(chars/4) for each side.
        [$input, $output] = $ref->invoke($service, 'Plain text response of 40 chars long padded!!', 80);

        self::assertSame(20, $input); // 80 / 4
        self::assertGreaterThan(0, $output);
    }
}

/**
 * Tiny no-op logger to avoid pulling in the symfony logger contracts.
 */
class NullLogger extends AbstractLogger
{
    /** @param array<mixed> $context */
    public function log($level, \Stringable|string $message, array $context = []): void {}
}
