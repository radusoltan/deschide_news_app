<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Clustering\ClusteringService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cluster:run',
    description: 'Run clustering on unclustered PressReleases',
)]
class ClusterRunCommand extends Command
{
    public function __construct(
        private readonly ClusteringService $clusteringService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Time window (e.g., "24h", "48h", "7d")', '24h')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be clustered without persisting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $sinceStr = $input->getOption('since');
        $since = $this->parseSince($sinceStr);

        $io->title('StoryCluster: Clustering PressReleases');
        $io->writeln(sprintf('Time window: since %s', $since->format('Y-m-d H:i:s')));

        if ($dryRun) {
            $io->note('DRY RUN mode — no changes will be persisted');
        }

        try {
            $processed = $this->clusteringService->clusterNewPressReleases($since, $dryRun);

            $io->success(sprintf(
                '%s: %d PressReleases processed',
                $dryRun ? 'DRY RUN' : 'Done',
                $processed,
            ));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Clustering failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function parseSince(string $since): \DateTimeImmutable
    {
        if (preg_match('/^(\d+)h$/', $since, $m)) {
            return new \DateTimeImmutable(sprintf('-%d hours', (int) $m[1]));
        }
        if (preg_match('/^(\d+)d$/', $since, $m)) {
            return new \DateTimeImmutable(sprintf('-%d days', (int) $m[1]));
        }

        return new \DateTimeImmutable('-24 hours');
    }
}
