<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\StoryCluster;
use App\Repository\StoryClusterRepository;
use App\Service\Clustering\ClusterSummaryService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cluster:summarize',
    description: 'Generate AI summaries for top StoryCluster entities using Gemini',
)]
class ClusterSummarizeCommand extends Command
{
    public function __construct(
        private readonly ClusterSummaryService $summaryService,
        private readonly StoryClusterRepository $clusterRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Max clusters to summarize', '20')
            ->addOption('cluster-id', null, InputOption::VALUE_REQUIRED, 'Summarize a specific cluster')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Regenerate even if summary already exists');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('StoryCluster: AI Summarization');

        $clusterId = $input->getOption('cluster-id');
        $limit = (int) $input->getOption('limit');
        $force = $input->getOption('force');

        if ($clusterId !== null) {
            $cluster = $this->clusterRepository->find((int) $clusterId);
            if ($cluster === null) {
                $io->error(sprintf('Cluster #%d not found', (int) $clusterId));
                return Command::FAILURE;
            }
            $clusters = [$cluster];
        } else {
            $clusters = $this->clusterRepository->findTopUnsummarized($limit, $force);
        }

        if ($clusters === []) {
            $io->info('No clusters to summarize');
            return Command::SUCCESS;
        }

        $io->writeln(sprintf('Summarizing %d clusters...', \count($clusters)));

        try {
            $success = 0;
            $failed = 0;

            foreach ($clusters as $cluster) {
                $io->write(sprintf(
                    '  #%d "%.60s" ... ',
                    $cluster->getId(),
                    $cluster->getPrimaryHeadline(),
                ));

                $result = $this->summaryService->summarize($cluster);

                if ($result) {
                    $io->writeln('<info>OK</info>');
                    $success++;
                } else {
                    $io->writeln('<error>FAILED</error>');
                    $failed++;
                }
            }

            $io->success(sprintf('Done: %d succeeded, %d failed', $success, $failed));

            return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Cluster summarization failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
