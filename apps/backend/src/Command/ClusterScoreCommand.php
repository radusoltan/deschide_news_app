<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\StoryCluster;
use App\Enum\StoryClusterStatus;
use App\Repository\StoryClusterRepository;
use App\Service\Clustering\AutoPromoteService;
use App\Service\Clustering\ImportanceScoreCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cluster:score',
    description: 'Recalculate importance scores for all active StoryCluster entities',
)]
class ClusterScoreCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly StoryClusterRepository $clusterRepository,
        private readonly ImportanceScoreCalculator $calculator,
        private readonly AutoPromoteService $autoPromoteService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('cluster-id', null, InputOption::VALUE_REQUIRED, 'Score only a specific cluster')
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Time window for active clusters', '48h')
            ->addOption('auto-promote', null, InputOption::VALUE_NONE, 'Auto-promote clusters with score > 0.7');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $clusterId = $input->getOption('cluster-id');
        $autoPromote = $input->getOption('auto-promote');
        $sinceStr = $input->getOption('since');

        $io->title('StoryCluster: Scoring');

        if ($clusterId !== null) {
            $cluster = $this->em->find(StoryCluster::class, (int) $clusterId);
            if ($cluster === null) {
                $io->error(sprintf('Cluster #%d not found', (int) $clusterId));
                return Command::FAILURE;
            }
            $clusters = [$cluster];
        } else {
            $since = $this->parseSince($sinceStr);
            $clusters = $this->clusterRepository->findActiveClustersInWindow($since);
        }

        $io->writeln(sprintf('Scoring %d clusters...', \count($clusters)));

        $scored = 0;
        $promoted = 0;

        foreach ($clusters as $cluster) {
            $oldScore = $cluster->getImportanceScore();
            $newScore = $this->calculator->calculate($cluster);
            $cluster->setImportanceScore($newScore);
            $scored++;

            // Auto-promote high-scoring clusters via AutoPromoteService
            if ($autoPromote && $this->autoPromoteService->isEligible($cluster)) {
                $pr = $this->autoPromoteService->promoteCluster($cluster);
                if ($pr !== null) {
                    $promoted++;
                    $io->writeln(sprintf(
                        '  [PROMOTED] #%d "%.60s" score=%.4f',
                        $cluster->getId(),
                        $cluster->getPrimaryHeadline(),
                        $newScore,
                    ));
                }
            }

            if ($output->isVerbose()) {
                $io->writeln(sprintf(
                    '  #%d: %.4f → %.4f "%s"',
                    $cluster->getId() ?? 0,
                    $oldScore,
                    $newScore,
                    mb_substr($cluster->getPrimaryHeadline(), 0, 60),
                ));
            }
        }

        $this->em->flush();

        $io->success(sprintf('Scored %d clusters, promoted %d', $scored, $promoted));

        return Command::SUCCESS;
    }

    private function parseSince(string $since): \DateTimeImmutable
    {
        if (preg_match('/^(\d+)h$/', $since, $m)) {
            return new \DateTimeImmutable(sprintf('-%d hours', (int) $m[1]));
        }
        if (preg_match('/^(\d+)d$/', $since, $m)) {
            return new \DateTimeImmutable(sprintf('-%d days', (int) $m[1]));
        }

        return new \DateTimeImmutable('-48 hours');
    }
}
