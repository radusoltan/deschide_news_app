<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Clustering\AutoPromoteService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cluster:auto-promote',
    description: 'Auto-promote high-scoring clusters to PressRelease (pending review)',
)]
class ClusterAutoPromoteCommand extends Command
{
    public function __construct(
        private readonly AutoPromoteService $autoPromoteService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('threshold', 't', InputOption::VALUE_REQUIRED, 'Override threshold (0.0-1.0)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List eligible clusters without promoting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $thresholdStr = $input->getOption('threshold');

        $threshold = $thresholdStr !== null ? (float) $thresholdStr : null;
        $effectiveThreshold = $threshold ?? $this->autoPromoteService->getThreshold();

        $io->title('StoryCluster: Auto-Promote');
        $io->writeln(sprintf('Threshold: %.2f%s', $effectiveThreshold, $dryRun ? ' (DRY RUN)' : ''));

        try {
            $result = $this->autoPromoteService->promoteHighScoreClusters($threshold, $dryRun);

            if (\count($result['clusters']) === 0) {
                $io->info('No eligible clusters found above threshold');
                return Command::SUCCESS;
            }

            foreach ($result['clusters'] as $c) {
                $io->writeln(sprintf(
                    '  %s #%d (score=%.4f) "%s"',
                    $dryRun ? '[ELIGIBLE]' : '[PROMOTED]',
                    $c['id'],
                    $c['score'],
                    mb_substr($c['headline'], 0, 70),
                ));
            }

            $io->success(sprintf(
                '%s: %d cluster%s %s',
                $dryRun ? 'DRY RUN' : 'Done',
                $result['promoted'],
                $result['promoted'] === 1 ? '' : 's',
                $dryRun ? 'eligible' : 'promoted',
            ));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Auto-promote failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
