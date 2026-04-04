<?php

declare(strict_types=1);

namespace App\Command\Editorial;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:dev:reset',
    description: 'Reset complet dev: drop DB + fixtures + RSS import + reindex',
)]
final class DevResetCommand extends Command
{
    /** @var list<array{step: string, status: string}> */
    private array $report = [];

    public function __construct(
        private readonly string $appEnv,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('skip-fixtures', null, InputOption::VALUE_NONE, 'Sare peste încărcarea fixtures')
            ->addOption('skip-import', null, InputOption::VALUE_NONE, 'Sare peste importul RSS (Supabase)')
            ->addOption('import-limit', null, InputOption::VALUE_REQUIRED, 'Limită articole pentru import RSS', '200')
            ->addOption('skip-elasticsearch', null, InputOption::VALUE_NONE, 'Sare peste reindexare Elasticsearch')
            ->addOption('enrich', null, InputOption::VALUE_NONE, 'Dispatch AI ingestion pentru toate articolele (async, necesită worker)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Dev Reset — Reset complet');

        if ($this->appEnv !== 'dev' && $this->appEnv !== 'test') {
            $io->error(sprintf(
                'Această comandă rulează DOAR în dev/test. Mediul curent: %s',
                $this->appEnv,
            ));

            return Command::FAILURE;
        }

        if ($input->isInteractive()) {
            $confirm = $io->confirm(
                'Ești sigur? Aceasta va ȘTERGE toate datele din DB.',
                false,
            );

            if (!$confirm) {
                $io->warning('Operațiune anulată.');

                return Command::SUCCESS;
            }
        }

        $skipFixtures = $input->getOption('skip-fixtures');
        $skipImport = $input->getOption('skip-import');
        $importLimit = (int) $input->getOption('import-limit');
        $skipEs = $input->getOption('skip-elasticsearch');

        // Step 1: Drop database schema
        $this->runStep($io, 'Drop database schema', fn () => $this->runSubCommand($output, 'doctrine:schema:drop', [
            '--force' => true,
            '--full-database' => true,
        ]));

        // Clear EM identity map after schema drop
        $this->em->clear();

        // Step 2: Run migrations
        $this->runStep($io, 'Run migrations', fn () => $this->runSubCommand($output, 'doctrine:migrations:migrate'));

        // Step 3: Load fixtures
        if (!$skipFixtures) {
            // Reset EM identity map
            $this->em->clear();
            if (!$this->em->isOpen()) {
                $io->warning('EntityManager is closed, fixtures may fail.');
            }
            $this->runStep($io, 'Load fixtures', fn () => $this->runSubCommand($output, 'doctrine:fixtures:load'));
            // Clear EM so RSS import queries hit the DB fresh
            $this->em->clear();
        } else {
            $this->report[] = ['step' => 'Load fixtures', 'status' => 'SKIPPED'];
        }

        // Step 4: Import articles from deschide.md via Supabase
        if (!$skipImport && !$skipFixtures) {
            $this->runStep($io, sprintf('Import RSS (%d articles)', $importLimit), fn () => $this->runSubCommand($output, 'app:import:rss-feed', [
                '--limit' => (string) $importLimit,
                '--no-translate' => true,
            ]));
        } else {
            $this->report[] = ['step' => 'Import RSS', 'status' => 'SKIPPED'];
        }

        // Step 5: Clear Redis cache
        $this->runStep($io, 'Clear cache pools', fn () => $this->runSubCommand($output, 'cache:pool:clear', [
            'pools' => ['cache.global_clearer'],
        ]));

        // Step 6: Reindex Elasticsearch
        if (!$skipEs) {
            $this->runStep($io, 'Reindex Elasticsearch', fn () => $this->runSubCommand($output, 'app:elasticsearch:index-articles'));
        } else {
            $this->report[] = ['step' => 'Reindex Elasticsearch', 'status' => 'SKIPPED'];
        }

        // Step 7: Dispatch AI ingestion (async, requires messenger worker)
        if ($input->getOption('enrich')) {
            $this->runStep($io, 'Dispatch AI ingestion (async)', fn () => $this->runSubCommand($output, 'app:editorial:batch-ingest', [
                '--batch' => (string) $importLimit,
            ]));
        } else {
            $this->report[] = ['step' => 'AI ingestion', 'status' => 'SKIPPED'];
        }

        // Summary
        $io->section('Raport final');
        $io->table(
            ['Pas', 'Status'],
            array_map(fn (array $r) => [$r['step'], $r['status']], $this->report),
        );

        $hasFailures = \count(array_filter($this->report, fn (array $r) => $r['status'] === 'FAILED')) > 0;

        if ($hasFailures) {
            $io->warning('Reset completat cu erori. Verificați raportul de mai sus.');

            return Command::FAILURE;
        }

        $io->success('Reset complet finalizat cu succes.');

        return Command::SUCCESS;
    }

    private function runStep(SymfonyStyle $io, string $stepName, callable $action): void
    {
        $io->write("  {$stepName}... ");

        try {
            $exitCode = $action();

            if ($exitCode === Command::SUCCESS || $exitCode === 0) {
                $this->report[] = ['step' => $stepName, 'status' => 'OK'];
                $io->writeln('<info>OK</info>');
            } else {
                $this->report[] = ['step' => $stepName, 'status' => 'FAILED'];
                $io->writeln('<error>FAILED</error>');
            }
        } catch (\Throwable $e) {
            $this->report[] = ['step' => $stepName, 'status' => 'FAILED'];
            $io->writeln(sprintf('<error>FAILED: %s</error>', $e->getMessage()));
        }
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function runSubCommand(OutputInterface $output, string $commandName, array $arguments = []): int
    {
        $command = $this->getApplication()?->find($commandName);

        if ($command === null) {
            throw new \RuntimeException("Command not found: {$commandName}");
        }

        $input = new ArrayInput($arguments);
        $input->setInteractive(false);

        return $command->run($input, $output);
    }
}
