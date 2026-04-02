<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Metrics\EditorialMetricsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:metrics:dashboard',
    description: 'Dashboard metrici editoriale',
)]
final class MetricsDashboardCommand extends Command
{
    public function __construct(
        private readonly EditorialMetricsService $metricsService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('period', 'p', InputOption::VALUE_REQUIRED, 'Ultimele N zile', '30')
            ->addOption('json', null, InputOption::VALUE_NONE, 'Output JSON pentru integrare')
            ->addOption('vault', null, InputOption::VALUE_NONE, 'Scrie snapshot în vault')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('period');
        $jsonOutput = $input->getOption('json');
        $writeVault = $input->getOption('vault');

        $to = new \DateTimeImmutable('now');
        $from = $to->modify("-{$days} days");

        $metrics = $this->metricsService->collectMetrics($from, $to);

        if ($jsonOutput) {
            $output->writeln(json_encode($metrics, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE));

            return Command::SUCCESS;
        }

        // Formatted dashboard output
        $vol = $metrics['volume'];
        $trans = $metrics['translations'];
        $qual = $metrics['quality'];
        $es = $metrics['elasticsearch'];
        $pipe = $metrics['pipeline'];
        $vault = $metrics['vault'] ?? null;

        $io->writeln('');
        $io->writeln('<fg=cyan>═══════════════════════════════════════════</>');
        $io->writeln('<fg=cyan>  DESCHIDE NEWS — Dashboard Metrici</>');
        $io->writeln(\sprintf('<fg=cyan>  Perioadă: %s — %s</>', $from->format('Y-m-d'), $to->format('Y-m-d')));
        $io->writeln('<fg=cyan>═══════════════════════════════════════════</>');
        $io->writeln('');

        $io->writeln(\sprintf('  <fg=white>VOLUM</> ............ <info>%s</info> articole (<info>%s</info>/zi)',
            number_format($vol['total']),
            $vol['per_day'],
        ));

        $io->writeln(\sprintf('  <fg=white>TRILINGVE</> ........ <info>%s%%</info> complete',
            $trans['trilingual_pct'],
        ));

        $io->writeln(\sprintf('  <fg=white>CALITATE</> ......... <info>%s%%</info> revizuite (%d auto-generate)',
            $qual['reviewed_pct'],
            $qual['auto_generated'],
        ));

        if ($es['enabled'] ?? false) {
            $io->writeln(\sprintf('  <fg=white>ELASTICSEARCH</> .... <info>%s</info> indexate (<info>%sms</info>)',
                number_format($es['indexed']),
                $es['latency_ms'] ?? '-',
            ));
        } else {
            $io->writeln('  <fg=white>ELASTICSEARCH</> .... <fg=yellow>dezactivat</>');
        }

        $io->writeln(\sprintf('  <fg=white>PIPELINE AZI</> ..... <info>%d</info> create, <info>%d</info> traduse, %s',
            $pipe['created_today'],
            $pipe['translated_today'],
            $pipe['failed_today'] > 0
                ? "<fg=red>{$pipe['failed_today']} erori</>"
                : '<fg=green>0 erori</>',
        ));

        if ($vault !== null) {
            $io->writeln(\sprintf('  <fg=white>VAULT</> ............ <info>%d</info> note (<info>%s%%</info> orfane)',
                $vault['total_notes'],
                $vault['orphan_pct'],
            ));
        }

        $io->writeln('');

        // Top categories table
        if (!empty($vol['by_category'])) {
            $io->section('Top categorii (perioadă)');
            $catRows = array_map(fn ($c) => [$c['slug'], $c['cnt']], $vol['by_category']);
            $io->table(['Categorie', 'Articole'], $catRows);
        }

        // Translation status breakdown
        if (!empty($trans['status_breakdown'])) {
            $io->section('Status traduceri');
            $statusRows = array_map(fn ($k, $v) => [$k, $v], array_keys($trans['status_breakdown']), $trans['status_breakdown']);
            $io->table(['Status', 'Articole'], $statusRows);
        }

        // Write vault snapshot if requested
        if ($writeVault) {
            $this->metricsService->writeVaultSnapshot($metrics);
            $io->success('Snapshot metrici scris în vault.');
        }

        return Command::SUCCESS;
    }
}
