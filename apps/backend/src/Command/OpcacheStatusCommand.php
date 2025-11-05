<?php

declare(strict_types=1);

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:opcache:status',
    description: 'Display OPcache status and statistics'
)]
class OpcacheStatusCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('reset', 'r', InputOption::VALUE_NONE, 'Reset OPcache')
            ->addOption('detailed', 'd', InputOption::VALUE_NONE, 'Show detailed statistics');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!\function_exists('opcache_get_status')) {
            $io->error('OPcache is not enabled');

            return Command::FAILURE;
        }

        if ($input->getOption('reset')) {
            if (opcache_reset()) {
                $io->success('OPcache has been reset successfully');
            } else {
                $io->error('Failed to reset OPcache');

                return Command::FAILURE;
            }
        }

        $status = opcache_get_status(false);
        $config = opcache_get_configuration();

        if (!$status) {
            $io->error('Unable to retrieve OPcache status');

            return Command::FAILURE;
        }

        $io->title('OPcache Status');

        // General Information
        $io->section('General Information');
        $io->horizontalTable(
            ['Setting', 'Value'],
            [
                ['Enabled', $config['directives']['opcache.enable'] ? '✅ Yes' : '❌ No'],
                ['JIT', $config['directives']['opcache.jit'] ?: 'Disabled'],
                ['Memory Consumption', $this->formatBytes($config['directives']['opcache.memory_consumption'] * 1024 * 1024)],
                ['Strings Buffer', $this->formatBytes($config['directives']['opcache.interned_strings_buffer'] * 1024 * 1024)],
                ['Max Accelerated Files', number_format($config['directives']['opcache.max_accelerated_files'])],
            ]
        );

        // Memory Usage
        $io->section('Memory Usage');
        $memory = $status['memory_usage'];
        $usedMemory = $memory['used_memory'];
        $freeMemory = $memory['free_memory'];
        $totalMemory = $usedMemory + $freeMemory;
        $usagePercent = ($usedMemory / $totalMemory) * 100;

        $io->horizontalTable(
            ['Metric', 'Value'],
            [
                ['Used Memory', $this->formatBytes($usedMemory)],
                ['Free Memory', $this->formatBytes($freeMemory)],
                ['Total Memory', $this->formatBytes($totalMemory)],
                ['Usage Percentage', \sprintf('%.2f%%', $usagePercent)],
            ]
        );

        // Cache Statistics
        $io->section('Cache Statistics');
        $stats = $status['opcache_statistics'];
        $hitRate = ($stats['hits'] / max($stats['hits'] + $stats['misses'], 1)) * 100;

        $io->horizontalTable(
            ['Metric', 'Value'],
            [
                ['Cached Scripts', number_format($stats['num_cached_scripts'])],
                ['Cached Keys', number_format($stats['num_cached_keys'])],
                ['Max Cached Keys', number_format($stats['max_cached_keys'])],
                ['Hits', number_format($stats['hits'])],
                ['Misses', number_format($stats['misses'])],
                ['Hit Rate', \sprintf('%.2f%%', $hitRate)],
            ]
        );

        // JIT Statistics (if available)
        if (isset($status['jit'])) {
            $io->section('JIT Statistics');
            $jit = $status['jit'];

            $io->horizontalTable(
                ['Metric', 'Value'],
                [
                    ['Enabled', $jit['enabled'] ? '✅ Yes' : '❌ No'],
                    ['Buffer Size', $this->formatBytes($jit['buffer_size'] ?? 0)],
                    ['Buffer Free', $this->formatBytes($jit['buffer_free'] ?? 0)],
                ]
            );
        }

        // Detailed Information
        if ($input->getOption('detailed') && !empty($status['scripts'])) {
            $io->section('Cached Scripts (Top 10 by Memory)');

            $scripts = $status['scripts'];
            usort($scripts, fn ($a, $b) => $b['memory_consumption'] <=> $a['memory_consumption']);
            $topScripts = \array_slice($scripts, 0, 10);

            $rows = [];
            foreach ($topScripts as $script) {
                $rows[] = [
                    basename($script['full_path']),
                    $this->formatBytes($script['memory_consumption']),
                    number_format($script['hits']),
                ];
            }

            $io->table(['Script', 'Memory', 'Hits'], $rows);
        }

        // Recommendations
        if ($usagePercent > 90) {
            $io->warning('OPcache memory usage is above 90%. Consider increasing opcache.memory_consumption');
        }

        if ($hitRate < 95) {
            $io->note('Cache hit rate is below 95%. This is normal after restart or with frequent code changes.');
        }

        return Command::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return \sprintf('%.2f GB', $bytes / 1073741824);
        }
        if ($bytes >= 1048576) {
            return \sprintf('%.2f MB', $bytes / 1048576);
        }
        if ($bytes >= 1024) {
            return \sprintf('%.2f KB', $bytes / 1024);
        }

        return $bytes . ' B';
    }
}
