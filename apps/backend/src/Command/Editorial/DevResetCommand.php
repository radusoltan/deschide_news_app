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
    description: 'Reset complet dev: drop DB + purge vault + fixtures + RSS import + vault sync',
)]
final class DevResetCommand extends Command
{
    /** @var list<array{step: string, status: string}> */
    private array $report = [];

    public function __construct(
        private readonly string $appEnv,
        private readonly string $vaultPath,
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
            ->addOption('skip-vault-sync', null, InputOption::VALUE_NONE, 'Sare peste sincronizarea vault (doar DB reset)')
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
                'Ești sigur? Aceasta va ȘTERGE toate datele din DB și vault.',
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
        $skipVault = $input->getOption('skip-vault-sync');

        // Step 1: Purge vault orphans
        if (!$skipVault) {
            $this->runStep($io, 'Purge vault orphans', fn () => $this->runSubCommand($output, 'app:vault:purge', ['--force' => true]));
        } else {
            $this->report[] = ['step' => 'Purge vault orphans', 'status' => 'SKIPPED'];
        }

        // Step 2: Clear vault articles directory
        if (!$skipVault) {
            $this->runStep($io, 'Clear vault articles/', fn () => $this->clearVaultArticles());
        } else {
            $this->report[] = ['step' => 'Clear vault articles/', 'status' => 'SKIPPED'];
        }

        // Step 3: Drop database schema
        $this->runStep($io, 'Drop database schema', fn () => $this->runSubCommand($output, 'doctrine:schema:drop', [
            '--force' => true,
            '--full-database' => true,
        ]));

        // Clear EM identity map after schema drop (stale entities from vault:purge)
        $this->em->clear();

        // Step 4: Run migrations
        $this->runStep($io, 'Run migrations', fn () => $this->runSubCommand($output, 'doctrine:migrations:migrate'));

        // Step 5: Load fixtures
        if (!$skipFixtures) {
            // Reset EM identity map (stale entities from vault:purge or previous runs)
            $this->em->clear();
            if (!$this->em->isOpen()) {
                // EM closed by a prior exception — nothing we can do in-process
                $io->warning('EntityManager is closed, fixtures may fail.');
            }
            $this->runStep($io, 'Load fixtures', fn () => $this->runSubCommand($output, 'doctrine:fixtures:load'));
            // Clear EM so RSS import queries hit the DB fresh
            $this->em->clear();
        } else {
            $this->report[] = ['step' => 'Load fixtures', 'status' => 'SKIPPED'];
        }

        // Step 6: Import articles from deschide.md via Supabase
        if (!$skipImport && !$skipFixtures) {
            $this->runStep($io, sprintf('Import RSS (%d articles)', $importLimit), fn () => $this->runSubCommand($output, 'app:import:rss-feed', [
                '--limit' => (string) $importLimit,
                '--no-translate' => true,
            ]));
        } else {
            $this->report[] = ['step' => 'Import RSS', 'status' => 'SKIPPED'];
        }

        // Step 7: Sync vault from DB
        if (!$skipVault) {
            $this->runStep($io, 'Sync vault from DB', fn () => $this->runSubCommand($output, 'app:vault:sync-from-db', [
                '--all' => true,
            ]));
        } else {
            $this->report[] = ['step' => 'Sync vault from DB', 'status' => 'SKIPPED'];
        }

        // Step 8: Clear Redis cache
        $this->runStep($io, 'Clear cache pools', fn () => $this->runSubCommand($output, 'cache:pool:clear', [
            'pools' => ['cache.global_clearer'],
        ]));

        // Step 9: Reindex Elasticsearch
        if (!$skipEs) {
            $this->runStep($io, 'Reindex Elasticsearch', fn () => $this->runSubCommand($output, 'app:elasticsearch:index-articles'));
        } else {
            $this->report[] = ['step' => 'Reindex Elasticsearch', 'status' => 'SKIPPED'];
        }

        // Step 10: Dispatch AI ingestion (async, requires messenger worker)
        if ($input->getOption('enrich') && !$skipVault) {
            $this->runStep($io, 'Dispatch AI ingestion (async)', fn () => $this->runSubCommand($output, 'app:editorial:batch-ingest', [
                '--batch' => (string) $importLimit,
            ]));
        } else {
            $this->report[] = ['step' => 'AI ingestion', 'status' => 'SKIPPED'];
        }

        // Step 11: Verify vault consistency
        if (!$skipVault) {
            $this->runStep($io, 'Verify vault consistency', fn () => $this->runSubCommand($output, 'app:vault:verify'));
        } else {
            $this->report[] = ['step' => 'Verify vault consistency', 'status' => 'SKIPPED'];
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

    private function clearVaultArticles(): int
    {
        $articlesDir = $this->vaultPath . '/articles';

        if (!is_dir($articlesDir)) {
            return Command::SUCCESS;
        }

        $this->removeDirectoryContents($articlesDir);

        return Command::SUCCESS;
    }

    private function removeDirectoryContents(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
    }
}
