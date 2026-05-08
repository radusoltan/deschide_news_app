<?php

declare(strict_types=1);

namespace App\Command\Settings;

use App\Entity\Ai\LlmAgentCallLog;
use App\Repository\Ai\LlmAgentCallLogRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Aggregate LLM invocation cost / duration / token metrics per agent and per
 * model.
 *
 * Sprint 55 shipped this as a grep-based MVP over the Monolog rotation
 * files; Sprint 56 T56.09 promotes it to a DB query against the
 * {@see LlmAgentCallLog} entity so historical windows beyond the log
 * rotation cycle and high-volume extended smokes (T56.12) remain queryable.
 *
 * Output shape is preserved from S55 for backwards compatibility with any
 * operator muscle memory / analytics scripts that consume the JSON form:
 * `{ agents: { <agentId>: { models: { <model>: bucket }, total: bucket_with_avg } }, total: bucket_with_avg }`.
 *
 * Usage:
 *   # last 24h, all agents, table output
 *   symfony console app:editorial:llm-cost-summary
 *
 *   # last hour only, filter to flash_writer / developing_story_writer
 *   symfony console app:editorial:llm-cost-summary --since=1h --agent='*_writer'
 *
 *   # JSON output for scripting
 *   symfony console app:editorial:llm-cost-summary --format=json --since=7d
 */
#[AsCommand(
    name: 'app:editorial:llm-cost-summary',
    description: 'Aggregate LlmAgentCallLog rows (per-agent, per-model cost + token rollup)',
)]
class LlmCostSummaryCommand extends Command
{
    public function __construct(
        private readonly LlmAgentCallLogRepository $repository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'since',
                null,
                InputOption::VALUE_REQUIRED,
                'Lookback window — "1h", "24h", "7d" etc. (any strtotime-compatible relative spec)',
                '24h',
            )
            ->addOption(
                'agent',
                null,
                InputOption::VALUE_REQUIRED,
                'Filter by agent id (glob pattern). Example: --agent="*_writer"',
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_REQUIRED,
                'Output format: table | json',
                'table',
            );
    }

    /**
     * Parse the short-form `--since` value into a DateTimeImmutable. Kept
     * verbatim from the S55 grep implementation — PHP's strtotime mis-parses
     * bare suffixes like "-24h" / "-30d" (returns FUTURE instead of past),
     * so the manual mapping is still worth the lines.
     */
    private function parseSince(string $raw): ?\DateTimeImmutable
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^(\d+)\s*([mhdw])$/i', $trimmed, $m) === 1) {
            $value = (int) $m[1];
            $unit = strtolower($m[2]);
            $relative = match ($unit) {
                'm' => sprintf('-%d minutes', $value),
                'h' => sprintf('-%d hours', $value),
                'd' => sprintf('-%d days', $value),
                'w' => sprintf('-%d weeks', $value),
                default => null,
            };
            if ($relative === null) {
                return null;
            }

            try {
                return new \DateTimeImmutable($relative);
            } catch (\Exception) {
                return null;
            }
        }

        // Fall back to strtotime for explicit forms ("24 hours ago", "-7 days").
        $ts = strtotime('-' . $trimmed);
        if ($ts === false || $ts > time()) {
            return null;
        }

        return (new \DateTimeImmutable())->setTimestamp($ts);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $format = (string) $input->getOption('format');
        if (!\in_array($format, ['table', 'json'], true)) {
            $io->error('Invalid --format. Use "table" or "json".');

            return Command::FAILURE;
        }

        $sinceRaw = (string) $input->getOption('since');
        $since = $this->parseSince($sinceRaw);
        if ($since === null) {
            $io->error(sprintf('Invalid --since value "%s". Use formats like "1h", "24h", "7d".', $sinceRaw));

            return Command::FAILURE;
        }

        $agentFilter = $input->getOption('agent');
        if ($agentFilter !== null) {
            $agentFilter = (string) $agentFilter;
        }

        $rows = $this->repository->findInTimeRange($since, new \DateTimeImmutable());
        if ($agentFilter !== null && $agentFilter !== '') {
            $rows = array_values(array_filter(
                $rows,
                static fn (LlmAgentCallLog $r): bool => fnmatch($agentFilter, $r->getAgentName()),
            ));
        }

        $rollup = $this->aggregate($rows);

        if ($format === 'json') {
            $output->writeln((string) json_encode($rollup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return Command::SUCCESS;
        }

        $this->renderTable($io, $rollup, $sinceRaw, $agentFilter);

        return Command::SUCCESS;
    }

    /**
     * @param list<LlmAgentCallLog> $rows
     * @return array{agents: array<string, array{models: array<string, array<string, int|float>>, total: array<string, int|float>}>, total: array<string, int|float>}
     */
    private function aggregate(array $rows): array
    {
        /** @var array<string, array<string, array<string, int|float>>> $byAgentModel */
        $byAgentModel = [];
        $grandTotal = $this->zeroBucket();

        foreach ($rows as $row) {
            $agentId = $row->getAgentName();
            $model = $row->getModel() ?? 'unknown';

            $byAgentModel[$agentId][$model] ??= $this->zeroBucket();
            $bucket = &$byAgentModel[$agentId][$model];
            $bucket['calls']++;
            $bucket['input_tokens'] += $row->getInputTokenCount();
            $bucket['output_tokens'] += $row->getOutputTokenCount();
            $bucket['cache_read_tokens'] += $row->getCacheReadTokenCount();
            $bucket['cache_creation_tokens'] += $row->getCacheCreationTokenCount();
            $bucket['cost_usd'] += $row->getCostUsd();
            $bucket['duration_ms'] += $row->getDurationMs();
            unset($bucket);

            $grandTotal['calls']++;
            $grandTotal['input_tokens'] += $row->getInputTokenCount();
            $grandTotal['output_tokens'] += $row->getOutputTokenCount();
            $grandTotal['cache_read_tokens'] += $row->getCacheReadTokenCount();
            $grandTotal['cache_creation_tokens'] += $row->getCacheCreationTokenCount();
            $grandTotal['cost_usd'] += $row->getCostUsd();
            $grandTotal['duration_ms'] += $row->getDurationMs();
        }

        $agentsOut = [];
        foreach ($byAgentModel as $agentId => $modelBuckets) {
            $agentTotal = $this->zeroBucket();
            $models = [];
            foreach ($modelBuckets as $model => $bucket) {
                $models[$model] = $this->withAverage($bucket);
                $agentTotal['calls'] += $bucket['calls'];
                $agentTotal['input_tokens'] += $bucket['input_tokens'];
                $agentTotal['output_tokens'] += $bucket['output_tokens'];
                $agentTotal['cache_read_tokens'] += $bucket['cache_read_tokens'];
                $agentTotal['cache_creation_tokens'] += $bucket['cache_creation_tokens'];
                $agentTotal['cost_usd'] += $bucket['cost_usd'];
                $agentTotal['duration_ms'] += $bucket['duration_ms'];
            }
            $agentsOut[$agentId] = [
                'models' => $models,
                'total' => $this->withAverage($agentTotal),
            ];
        }

        // Sort agents by total cost descending so the most expensive surface first.
        uasort(
            $agentsOut,
            static fn (array $a, array $b): int => ($b['total']['cost_usd'] ?? 0) <=> ($a['total']['cost_usd'] ?? 0),
        );

        return [
            'agents' => $agentsOut,
            'total' => $this->withAverage($grandTotal),
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function zeroBucket(): array
    {
        return [
            'calls' => 0,
            'input_tokens' => 0,
            'output_tokens' => 0,
            'cache_read_tokens' => 0,
            'cache_creation_tokens' => 0,
            'cost_usd' => 0.0,
            'duration_ms' => 0,
        ];
    }

    /**
     * @param array<string, int|float> $bucket
     * @return array<string, int|float>
     */
    private function withAverage(array $bucket): array
    {
        $calls = (int) ($bucket['calls'] ?? 0);
        $bucket['avg_cost_usd'] = $calls > 0 ? ((float) $bucket['cost_usd']) / $calls : 0.0;

        return $bucket;
    }

    /**
     * @param array{agents: array<string, array<string, mixed>>, total: array<string, int|float>} $rollup
     */
    private function renderTable(SymfonyStyle $io, array $rollup, string $since, ?string $agentFilter): void
    {
        $io->title(sprintf(
            'LLM agent cost summary — since %s%s',
            $since,
            $agentFilter !== null ? sprintf(' (agent filter: %s)', $agentFilter) : '',
        ));

        if ($rollup['agents'] === []) {
            $io->warning('No llm_agent_call_log rows matched the filters.');

            return;
        }

        $table = new Table($io);
        $table->setHeaders([
            'Agent', 'Model', 'Calls', 'In tokens', 'Out tokens', 'Cache read', 'Cost USD', 'Avg cost',
        ]);

        foreach ($rollup['agents'] as $agentId => $agentData) {
            /** @var array<string, array<string, int|float>> $models */
            $models = $agentData['models'];
            foreach ($models as $model => $bucket) {
                $table->addRow([
                    $agentId,
                    $model,
                    (string) (int) $bucket['calls'],
                    (string) (int) $bucket['input_tokens'],
                    (string) (int) $bucket['output_tokens'],
                    (string) (int) $bucket['cache_read_tokens'],
                    sprintf('%.4f', (float) $bucket['cost_usd']),
                    sprintf('%.4f', (float) $bucket['avg_cost_usd']),
                ]);
            }
        }

        $total = $rollup['total'];
        $table->addRow(new TableSeparator());
        $table->addRow([
            '<info>TOTAL</info>',
            '',
            (string) (int) $total['calls'],
            (string) (int) $total['input_tokens'],
            (string) (int) $total['output_tokens'],
            (string) (int) $total['cache_read_tokens'],
            sprintf('%.4f', (float) $total['cost_usd']),
            sprintf('%.4f', (float) $total['avg_cost_usd']),
        ]);

        $table->render();
    }
}
