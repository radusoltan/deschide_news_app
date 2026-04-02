<?php

declare(strict_types=1);

namespace App\Command\Editorial;

use App\Service\Editorial\DossierGenerationService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:editorial:generate-dossiers',
    description: 'Generate narrative dossiers for MOCs with enough recent articles',
)]
final class GenerateDossiersCommand extends Command
{
    public function __construct(
        private readonly DossierGenerationService $dossierService,
        private readonly string $vaultPath = '',
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('moc', null, InputOption::VALUE_REQUIRED, 'Specific MOC file (e.g., MOC-Integrare-UE.md)')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Process all MOCs above threshold')
            ->addOption('threshold', 't', InputOption::VALUE_REQUIRED, 'Minimum articles for dossier', '5')
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Look back N days for articles', '30')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without writing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->vaultPath === '') {
            $io->error('VAULT_PATH environment variable not configured');

            return Command::FAILURE;
        }

        $threshold = (int) $input->getOption('threshold');
        $days = (int) $input->getOption('days');
        $dryRun = $input->getOption('dry-run');
        $specificMoc = $input->getOption('moc');

        $io->title('Dossier Generation');

        if ($specificMoc !== null) {
            $io->info("Processing specific MOC: {$specificMoc}");
            $mocs = [[
                'moc' => $specificMoc,
                'path' => "{$this->vaultPath}/mocs/{$specificMoc}",
                'articleCount' => 0,
            ]];
        } elseif ($input->getOption('all')) {
            $mocs = $this->dossierService->detectMOCsNeedingDossier($this->vaultPath, $threshold);
            $io->info(\count($mocs) . " MOCs detected above threshold ({$threshold} articles)");
        } else {
            $io->error('Provide --moc=FILE or --all');

            return Command::FAILURE;
        }

        if ($mocs === []) {
            $io->note('No MOCs need dossier generation');

            return Command::SUCCESS;
        }

        $io->table(
            ['MOC', 'Article Refs'],
            array_map(fn ($m) => [$m['moc'], $m['articleCount']], $mocs),
        );

        $generated = 0;

        foreach ($mocs as $moc) {
            $topicName = str_replace(['MOC-', '.md', '-'], ['', '', ' '], $moc['moc']);
            $io->section("Generating dossier: {$topicName}");

            $articles = $this->dossierService->getRecentArticlesForTopic($topicName, $days);
            $io->info(\count($articles) . ' articles found in DB');

            if ($articles === []) {
                $io->note('No articles found for this topic, skipping');

                continue;
            }

            if ($dryRun) {
                $io->note('Dry run — would generate dossier with ' . \count($articles) . ' articles');

                continue;
            }

            $path = $this->dossierService->generateDossier($moc['path'], $articles, $this->vaultPath);

            if ($path !== null) {
                $io->success("Dossier saved: {$path}");
                $generated++;
            } else {
                $io->warning("Failed to generate dossier for {$topicName}");
            }
        }

        $io->success("Done: {$generated} dossier(s) generated");

        return Command::SUCCESS;
    }
}
