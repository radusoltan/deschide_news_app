<?php

declare(strict_types=1);

namespace App\Command\Editorial;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Aggregate `llm_agent_call` log-line cost metrics into a human-readable
 * summary (Sprint 55 T55.16). MVP implementation — grep-based over the
 * rotation files in `var/log/`.
 *
 * @todo Sprint 56: migrate to a DB-backed LlmAgentCallLog entity so the
 *       summary can query historical windows past the log-rotation window
 *       and support per-minute aggregation. The grep approach breaks down at
 *       high volume (log rotation every 10 MB; prod will churn through the
 *       window in <1h once the pipeline is live).
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
    description: 'Aggregate llm_agent_call log-line metrics (per-agent, per-model cost + token rollup)',
)]
class LlmCostSummaryCommand extends Command
{
    /**
     * Regex capturing the Monolog line format `[timestamp] channel.LEVEL: llm_agent_call {JSON} [extra]`.
     * Matches only the `llm_agent_call` message; other log lines are passed over.
     */
    private const LINE_REGEX = '/^\[([^\]]+)\]\s+[^:]+:\s+llm_agent_call\s+(\{.*?\})\s+(\[.*?\]|\{.*?\})?\s*$/u';

    public function __construct(
        private readonly string $logDir,
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
            )
            ->addOption(
                'log-dir',
                null,
                InputOption::VALUE_REQUIRED,
                'Override the log directory (default: Symfony `%kernel.logs_dir%`). Useful in tests.',
            );
    }

    /**
     * Parse the short-form `--since` value into a unix timestamp.
     *
     * PHP's strtotime() mis-parses bare suffixes like "-24h" or "-30d"
     * (returns FUTURE instead of past), so we hand-roll the conversion here:
     *   12m → -12 minutes
     *   1h  → -1 hour
     *   24h → -24 hours
     *   7d  → -7 days
     *   3w  → -3 weeks
     * Also accepts explicit forms ("24 hours", "7 days").
     */
    private function parseSince(string $raw): ?int
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

            $ts = strtotime($relative);

            return $ts === false ? null : $ts;
        }

        // Fall back to strtotime for explicit forms ("24 hours ago", "-7 days").
        $ts = strtotime('-' . $trimmed);
        if ($ts === false || $ts > time()) {
            return null;
        }

        return $ts;
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
        $sinceTimestamp = $this->parseSince($sinceRaw);
        if ($sinceTimestamp === null) {
            $io->error(sprintf('Invalid --since value "%s". Use formats like "1h", "24h", "7d".', $sinceRaw));

            return Command::FAILURE;
        }

        $agentFilter = $input->getOption('agent');
        if ($agentFilter !== null) {
            $agentFilter = (string) $agentFilter;
        }

        $logDir = $input->getOption('log-dir') !== null
            ? (string) $input->getOption('log-dir')
            : $this->logDir;

        if (!is_dir($logDir)) {
            $io->error(sprintf('Log directory does not exist: %s', $logDir));

            return Command::FAILURE;
        }

        $entries = $this->collectEntries($logDir, $sinceTimestamp, $agentFilter);
        $rollup = $this->aggregate($entries);

        if ($format === 'json') {
            $output->writeln(json_encode($rollup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return Command::SUCCESS;
        }

        $this->renderTable($io, $rollup, $sinceRaw, $agentFilter);

        return Command::SUCCESS;
    }

    /**
     * Walks all `*.log` files in the given directory, parses `llm_agent_call`
     * lines, and filters by timestamp + agent glob.
     *
     * @return list<array<string, mixed>>
     */
    private function collectEntries(string $logDir, int $sinceTimestamp, ?string $agentFilter): array
    {
        $entries = [];

        $files = glob($logDir . '/*.log');
        if ($files === false) {
            return [];
        }

        foreach ($files as $file) {
            $handle = @fopen($file, 'r');
            if ($handle === false) {
                continue;
            }
            try {
                while (($line = fgets($handle)) !== false) {
                    $parsed = $this->parseLine($line);
                    if ($parsed === null) {
                        continue;
                    }

                    if ($parsed['timestamp'] < $sinceTimestamp) {
                        continue;
                    }

                    if ($agentFilter !== null && $agentFilter !== '' && !fnmatch($agentFilter, (string) ($parsed['agent_id'] ?? ''))) {
                        continue;
                    }

                    $entries[] = $parsed;
                }
            } finally {
                fclose($handle);
            }
        }

        return $entries;
    }

    /**
     * Extract timestamp + llm_agent_call JSON context from one log line.
     *
     * @return array<string, mixed>|null null when the line isn't an llm_agent_call entry or is malformed
     */
    private function parseLine(string $line): ?array
    {
        $line = rtrim($line, "\r\n");
        if ($line === '' || !str_contains($line, 'llm_agent_call')) {
            return null;
        }

        if (preg_match(self::LINE_REGEX, $line, $matches) !== 1) {
            return null;
        }

        $timestampStr = $matches[1];
        $jsonStr = $matches[2];

        $timestamp = strtotime($timestampStr);
        if ($timestamp === false) {
            return null;
        }

        try {
            /** @var array<string, mixed> $context */
            $context = json_decode($jsonStr, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        $context['timestamp'] = $timestamp;

        return $context;
    }

    /**
     * @param list<array<string, mixed>> $entries
     * @return array{agents: array<string, array<string, mixed>>, total: array<string, int|float>}
     */
    private function aggregate(array $entries): array
    {
        /** @var array<string, array<string, array<string, int|float>>> $byAgentModel */
        $byAgentModel = [];
        $grandTotal = $this->zeroBucket();

        foreach ($entries as $entry) {
            $agentId = (string) ($entry['agent_id'] ?? 'unknown');
            $model = (string) ($entry['model'] ?? 'unknown');

            $byAgentModel[$agentId][$model] ??= $this->zeroBucket();
            $bucket = &$byAgentModel[$agentId][$model];
            $bucket['calls']++;
            $bucket['input_tokens'] += (int) ($entry['input_tokens'] ?? 0);
            $bucket['output_tokens'] += (int) ($entry['output_tokens'] ?? 0);
            $bucket['cache_read_tokens'] += (int) ($entry['cache_read_tokens'] ?? 0);
            $bucket['cache_creation_tokens'] += (int) ($entry['cache_creation_tokens'] ?? 0);
            $bucket['cost_usd'] += (float) ($entry['cost_usd'] ?? 0.0);
            $bucket['duration_ms'] += (int) ($entry['duration_ms'] ?? 0);
            unset($bucket);

            $grandTotal['calls']++;
            $grandTotal['input_tokens'] += (int) ($entry['input_tokens'] ?? 0);
            $grandTotal['output_tokens'] += (int) ($entry['output_tokens'] ?? 0);
            $grandTotal['cache_read_tokens'] += (int) ($entry['cache_read_tokens'] ?? 0);
            $grandTotal['cache_creation_tokens'] += (int) ($entry['cache_creation_tokens'] ?? 0);
            $grandTotal['cost_usd'] += (float) ($entry['cost_usd'] ?? 0.0);
            $grandTotal['duration_ms'] += (int) ($entry['duration_ms'] ?? 0);
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
        uasort($agentsOut, static fn (array $a, array $b): int => ($b['total']['cost_usd'] ?? 0) <=> ($a['total']['cost_usd'] ?? 0));

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
            $io->warning('No llm_agent_call entries matched the filters.');

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
        $table->addRow(new \Symfony\Component\Console\Helper\TableSeparator());
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
