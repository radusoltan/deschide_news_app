<?php

declare(strict_types=1);

namespace App\Tests\Unit\Command\Editorial;

use App\Command\Editorial\LlmCostSummaryCommand;
use App\Entity\Editorial\LlmAgentCallLog;
use App\Repository\Editorial\LlmAgentCallLogRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Uid\Ulid;

/**
 * Unit test for {@see LlmCostSummaryCommand} after the T56.09 DB-backed
 * rewrite.
 *
 * Mocks the repository so the command exercises the aggregation and
 * rendering pure-functions without touching Postgres. The repository
 * itself is covered end-to-end by {@see \App\Tests\Integration\Repository\Editorial\LlmAgentCallLogRepositoryTest}.
 */
class LlmCostSummaryCommandTest extends TestCase
{
    private LlmAgentCallLogRepository&MockObject $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(LlmAgentCallLogRepository::class);
    }

    public function testJsonOutputAggregatesPerAgentAndPerModel(): void
    {
        // Two agents, three rows — flash_writer has 2 invocations (haiku +
        // gemini fallback models), legal_guard has 1. Aggregation groups by
        // (agent, model) and rolls up to (agent, total) and (grand total).
        $this->repository->method('findInTimeRange')->willReturn([
            $this->makeRow('flash_writer', 'claude-haiku-4-5', 1000, 2048, 512, 0.0125),
            $this->makeRow('flash_writer', 'gemini-2.5-flash', 4000, 0, 0, 0.0),
            $this->makeRow('legal_guard',  'claude-haiku-4-5',  500,  800, 100, 0.0045),
        ]);

        $tester = new CommandTester(new LlmCostSummaryCommand($this->repository));
        $exit = $tester->execute(['--since' => '1h', '--format' => 'json']);

        $this->assertSame(0, $exit);
        /** @var array<string, mixed> $out */
        $out = json_decode($tester->getDisplay(), true, 32, JSON_THROW_ON_ERROR);

        $this->assertArrayHasKey('agents', $out);
        $this->assertArrayHasKey('total', $out);
        // Two agents grouped.
        $this->assertCount(2, $out['agents']);

        // flash_writer rolled up across its two models.
        $flash = $out['agents']['flash_writer'];
        $this->assertSame(2, $flash['total']['calls']);
        $this->assertSame(2048, $flash['total']['input_tokens']);
        $this->assertSame(512, $flash['total']['output_tokens']);
        $this->assertEqualsWithDelta(0.0125, $flash['total']['cost_usd'], 1e-9);
        $this->assertArrayHasKey('claude-haiku-4-5', $flash['models']);
        $this->assertArrayHasKey('gemini-2.5-flash', $flash['models']);

        // Grand total across both agents.
        $this->assertSame(3, $out['total']['calls']);
        $this->assertEqualsWithDelta(0.0125 + 0.0045, $out['total']['cost_usd'], 1e-9);
        $this->assertArrayHasKey('avg_cost_usd', $out['total']);
    }

    public function testAgentFilterNarrowsResultSet(): void
    {
        $this->repository->method('findInTimeRange')->willReturn([
            $this->makeRow('flash_writer', 'claude-haiku-4-5', 1000, 10, 5, 0.001),
            $this->makeRow('legal_guard',  'claude-haiku-4-5',  500,  8, 2, 0.0005),
            $this->makeRow('developing_story_writer', 'claude-haiku-4-5', 1100, 12, 6, 0.0012),
        ]);

        $tester = new CommandTester(new LlmCostSummaryCommand($this->repository));
        $tester->execute(['--since' => '1h', '--agent' => '*_writer', '--format' => 'json']);

        /** @var array<string, mixed> $out */
        $out = json_decode($tester->getDisplay(), true, 32, JSON_THROW_ON_ERROR);
        $this->assertEqualsCanonicalizing(
            ['flash_writer', 'developing_story_writer'],
            array_keys($out['agents']),
        );
    }

    public function testTableOutputContainsExpectedColumnsAndTotal(): void
    {
        // Backwards-compat contract: column headers preserved verbatim from
        // the S55 grep-based implementation so dashboards / scripts that
        // snapshot the table don't need updates.
        $this->repository->method('findInTimeRange')->willReturn([
            $this->makeRow('flash_writer', 'claude-haiku-4-5', 1200, 2048, 512, 0.0175),
        ]);

        $tester = new CommandTester(new LlmCostSummaryCommand($this->repository));
        $exit = $tester->execute(['--since' => '1h']);
        $display = $tester->getDisplay();

        $this->assertSame(0, $exit);
        foreach (['Agent', 'Model', 'Calls', 'In tokens', 'Out tokens', 'Cache read', 'Cost USD', 'Avg cost'] as $header) {
            $this->assertStringContainsString($header, $display);
        }
        $this->assertStringContainsString('flash_writer', $display);
        $this->assertStringContainsString('TOTAL', $display);
    }

    public function testEmptyResultRendersExpectedWarning(): void
    {
        $this->repository->method('findInTimeRange')->willReturn([]);

        $tester = new CommandTester(new LlmCostSummaryCommand($this->repository));
        $tester->execute(['--since' => '24h']);
        $display = $tester->getDisplay();

        $this->assertStringContainsString('No llm_agent_call_log rows matched the filters', $display);
    }

    public function testInvalidSinceReturnsFailure(): void
    {
        $this->repository->expects($this->never())->method('findInTimeRange');

        $tester = new CommandTester(new LlmCostSummaryCommand($this->repository));
        $exit = $tester->execute(['--since' => 'garbage']);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('Invalid --since', $tester->getDisplay());
    }

    private function makeRow(
        string $agent,
        string $model,
        int $durationMs,
        int $inputTokens,
        int $outputTokens,
        float $costUsd,
    ): LlmAgentCallLog {
        return new LlmAgentCallLog(
            agentName: $agent,
            invocationId: (string) new Ulid(),
            promptHash: str_repeat('a', 64),
            durationMs: $durationMs,
            inputTokenCount: $inputTokens,
            outputTokenCount: $outputTokens,
            costUsd: $costUsd,
            model: $model,
        );
    }
}
