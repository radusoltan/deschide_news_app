<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\Editorial;

use App\Command\Editorial\LlmCostSummaryCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Unit test for {@see LlmCostSummaryCommand} (Sprint 55 T55.16).
 *
 * Uses a dedicated fixture log file and an isolated tmp dir per test so the
 * command's `--log-dir` override works cleanly. `--since` is set to a very
 * large window for the "include old rows" tests because the fixture
 * timestamps are anchored at 2026-04-18 — the sprint-55 development date.
 * Tests that filter by time prepare fresh logs with current timestamps.
 */
class LlmCostSummaryCommandTest extends TestCase
{
    private const FIXTURE_LOG = __DIR__ . '/../../../fixtures/editorial/logs/llm-cost-fixture.log';

    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/llm-cost-' . uniqid();
        mkdir($this->tmpDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        // Clean tmp dir.
        $files = glob($this->tmpDir . '/*') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($this->tmpDir);
    }

    public function testAggregationAcrossMultipleAgentsAndModels(): void
    {
        // Use a since window that fails strtotime into the future by design
        // is avoided — we write a fixture into tmp dir with CURRENT timestamps
        // so any `--since=1h|24h|7d` will include them.
        $this->seedTmpLog($this->buildCurrentFixture());

        $tester = new CommandTester(new LlmCostSummaryCommand($this->tmpDir));
        $exit = $tester->execute([
            '--since' => '1h',
            '--format' => 'json',
        ]);

        $this->assertSame(0, $exit);
        /** @var array<string, mixed> $out */
        $out = json_decode($tester->getDisplay(), true, 32, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('agents', $out);
        $this->assertArrayHasKey('total', $out);

        // Three distinct agents (flash_writer, style_guard, legal_guard).
        $this->assertCount(3, $out['agents']);

        // flash_writer total: 3 calls (haiku + gemini fallback).
        $flash = $out['agents']['flash_writer'];
        $this->assertSame(3, $flash['total']['calls']);
        $this->assertEqualsWithDelta(0.0125 + 0.0180 + 0.0045, $flash['total']['cost_usd'], 0.0001);

        // Grand total: 5 calls.
        $this->assertSame(5, $out['total']['calls']);
        $this->assertEqualsWithDelta(0.0125 + 0.0180 + 0.0090 + 0.0520 + 0.0045, $out['total']['cost_usd'], 0.0001);

        // Average is populated.
        $this->assertArrayHasKey('avg_cost_usd', $out['total']);
    }

    public function testSinceFilterExcludesOlderEntries(): void
    {
        // Seed TWO lines: one within the last 30 minutes, one 5 days old.
        $recent = $this->buildLine(new \DateTimeImmutable('-30 minutes'), 'flash_writer', 0.01);
        $old = $this->buildLine(new \DateTimeImmutable('-5 days'), 'flash_writer', 99.99);

        $this->seedTmpLog($recent . "\n" . $old . "\n");

        $tester = new CommandTester(new LlmCostSummaryCommand($this->tmpDir));
        $tester->execute([
            '--since' => '1h',
            '--format' => 'json',
        ]);

        $out = json_decode($tester->getDisplay(), true, 32, JSON_THROW_ON_ERROR);
        $this->assertSame(1, $out['total']['calls'], 'Only the 30-minute-old entry should count with --since=1h');
        $this->assertEqualsWithDelta(0.01, $out['total']['cost_usd'], 0.0001);
    }

    public function testAgentGlobFilter(): void
    {
        $this->seedTmpLog($this->buildCurrentFixture());

        $tester = new CommandTester(new LlmCostSummaryCommand($this->tmpDir));
        $tester->execute([
            '--since' => '1h',
            '--agent' => '*_writer',
            '--format' => 'json',
        ]);

        $out = json_decode($tester->getDisplay(), true, 32, JSON_THROW_ON_ERROR);
        // Only flash_writer entries (3) — style_guard and legal_guard filtered out.
        $this->assertCount(1, $out['agents']);
        $this->assertArrayHasKey('flash_writer', $out['agents']);
        $this->assertSame(3, $out['total']['calls']);
    }

    public function testInvalidFormatReturnsFailure(): void
    {
        $tester = new CommandTester(new LlmCostSummaryCommand($this->tmpDir));
        $exit = $tester->execute([
            '--format' => 'xml',
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Invalid --format', $tester->getDisplay());
    }

    public function testInvalidSinceReturnsFailure(): void
    {
        $tester = new CommandTester(new LlmCostSummaryCommand($this->tmpDir));
        $exit = $tester->execute([
            '--since' => 'garbage-window',
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Invalid --since', $tester->getDisplay());
    }

    public function testMissingLogDirReturnsFailure(): void
    {
        $tester = new CommandTester(new LlmCostSummaryCommand('/tmp/does-not-exist-' . uniqid()));
        $exit = $tester->execute([]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Log directory does not exist', $tester->getDisplay());
    }

    public function testTableFormatRendersAggregateRow(): void
    {
        $this->seedTmpLog($this->buildCurrentFixture());

        $tester = new CommandTester(new LlmCostSummaryCommand($this->tmpDir));
        $exit = $tester->execute([
            '--since' => '1h',
            '--format' => 'table',
        ]);

        $this->assertSame(0, $exit);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('LLM agent cost summary', $display);
        $this->assertStringContainsString('TOTAL', $display);
        $this->assertStringContainsString('flash_writer', $display);
        $this->assertStringContainsString('style_guard', $display);
    }

    public function testNonLlmLinesIgnored(): void
    {
        // Fixture file contains 5 llm_agent_call lines + 2 noise lines.
        // Build a file with the static fixture but re-timestamped to "now" so
        // the `--since=1h` window includes them.
        $this->seedTmpLog($this->buildCurrentFixture());

        // Add noise lines that should be ignored.
        $noise = implode("\n", [
            '[' . (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM) . '] app.DEBUG: unrelated stuff',
            '[' . (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM) . '] app.INFO: some_other_event {"x":1} []',
            'malformed line without timestamp brackets',
        ]);
        file_put_contents($this->tmpDir . '/noise.log', $noise . "\n");

        $tester = new CommandTester(new LlmCostSummaryCommand($this->tmpDir));
        $tester->execute([
            '--since' => '1h',
            '--format' => 'json',
        ]);

        $out = json_decode($tester->getDisplay(), true, 32, JSON_THROW_ON_ERROR);
        // Still 5 calls — the noise file contributed zero.
        $this->assertSame(5, $out['total']['calls']);
    }

    public function testFixtureFileParsesWithWideSinceWindow(): void
    {
        // Directly feed the committed fixture file; uses a since window that
        // reaches past its timestamps. This test guards the fixture format
        // so the empirical test docs (and the command's example usage) stay
        // in sync with the production Monolog output format.
        $fixtureDir = \dirname(self::FIXTURE_LOG);

        $tester = new CommandTester(new LlmCostSummaryCommand($fixtureDir));
        $tester->execute([
            '--since' => '365d',
            '--format' => 'json',
        ]);

        $out = json_decode($tester->getDisplay(), true, 32, JSON_THROW_ON_ERROR);
        $this->assertSame(5, $out['total']['calls'], 'Fixture must expose exactly 5 llm_agent_call entries');
    }

    private function seedTmpLog(string $content): void
    {
        file_put_contents($this->tmpDir . '/test.log', $content);
    }

    /**
     * Build a version of the static fixture with CURRENT timestamps so
     * `--since=1h` includes everything.
     */
    private function buildCurrentFixture(): string
    {
        $now = new \DateTimeImmutable();
        $lines = [
            $this->buildLine($now->modify('-20 minutes'), 'flash_writer', 0.0125, 'claude-haiku-4-5-20251001', 100, 200),
            $this->buildLine($now->modify('-15 minutes'), 'flash_writer', 0.0180, 'claude-haiku-4-5-20251001', 150, 250, 500),
            $this->buildLine($now->modify('-10 minutes'), 'style_guard', 0.0090, 'claude-haiku-4-5-20251001', 80, 120),
            $this->buildLine($now->modify('-5 minutes'), 'legal_guard', 0.0520, 'claude-sonnet-4-6', 200, 350),
            $this->buildLine($now->modify('-1 minute'), 'flash_writer', 0.0045, 'gemini-2.5-flash', 300, 400),
        ];

        return implode("\n", $lines) . "\n";
    }

    private function buildLine(
        \DateTimeImmutable $when,
        string $agentId,
        float $cost,
        string $model = 'claude-haiku-4-5-20251001',
        int $inputTokens = 100,
        int $outputTokens = 200,
        int $cacheRead = 0,
    ): string {
        $context = [
            'agent_id' => $agentId,
            'tier' => 'haiku',
            'model' => $model,
            'attempts' => 1,
            'transport' => 'claude_cli',
            'fallback_detected' => false,
            'input_tokens' => $inputTokens,
            'output_tokens' => $outputTokens,
            'cache_read_tokens' => $cacheRead,
            'cache_creation_tokens' => 0,
            'cost_usd' => $cost,
            'duration_ms' => 1500,
            'duration_api_ms' => 1400,
        ];

        return sprintf(
            '[%s] app.INFO: llm_agent_call %s []',
            $when->format('Y-m-d\TH:i:s.uP'),
            json_encode($context, JSON_UNESCAPED_SLASHES),
        );
    }
}
