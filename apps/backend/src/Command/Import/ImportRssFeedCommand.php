<?php

declare(strict_types=1);

namespace App\Command\Import;

use App\Service\Import\RssFeedImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:import:rss-feed',
    description: 'Importă articole din deschide.md via Supabase REST API (paginat, toate articolele)',
)]
final class ImportRssFeedCommand extends Command
{
    public function __construct(
        private readonly RssFeedImporter $importer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Număr maxim de articole de importat', '50')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulare — nu salvează în DB')
            ->addOption('no-translate', null, InputOption::VALUE_NONE, 'Nu dispatcha traduceri pe Messenger');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $limit = (int) $input->getOption('limit');
        $dryRun = (bool) $input->getOption('dry-run');
        $dispatchTranslations = !$input->getOption('no-translate');

        $io->title('Import deschide.md — Supabase REST API');

        if ($dryRun) {
            $io->warning('MOD DRY-RUN: nimic nu se salvează în baza de date.');
        }

        $io->info(\sprintf('Limit: %d | Traduceri: %s', $limit, $dispatchTranslations ? 'DA' : 'NU'));

        $stats = $this->importer->import($limit, $dryRun, $dispatchTranslations);

        $io->table(
            ['Metric', 'Valoare'],
            [
                ['Total procesate', (string) $stats['total']],
                ['Importate cu succes', (string) $stats['imported']],
                ['Skipped (deja existente)', (string) $stats['skipped']],
                ['Erori', (string) $stats['errors']],
            ],
        );

        if (!empty($stats['details'])) {
            $io->section('Detalii');
            foreach ($stats['details'] as $detail) {
                $io->writeln('  · ' . $detail);
            }
        }

        if ($stats['errors'] > 0) {
            $io->warning(\sprintf('Import finalizat cu %d erori.', $stats['errors']));

            return Command::FAILURE;
        }

        $io->success(\sprintf('Import finalizat: %d articole noi.', $stats['imported']));

        return Command::SUCCESS;
    }
}
