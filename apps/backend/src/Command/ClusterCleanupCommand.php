<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\AppSettingRepository;
use App\Repository\StoryClusterRepository;
use App\Service\Clustering\ClusteringService;
use App\Service\Clustering\ImportanceScoreCalculator;
use App\Service\Clustering\PressReleaseIndexManager;
use App\Service\Clustering\SemanticClusterVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cluster:cleanup',
    description: 'Cleanup, verify, or rebuild story clusters',
)]
class ClusterCleanupCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly StoryClusterRepository $clusterRepository,
        private readonly ClusteringService $clusteringService,
        private readonly ImportanceScoreCalculator $calculator,
        private readonly SemanticClusterVerifier $verifier,
        private readonly PressReleaseIndexManager $indexManager,
        private readonly AppSettingRepository $appSettings,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would change without persisting')
            ->addOption('rebuild', null, InputOption::VALUE_NONE, 'Wipe ALL clusters and re-cluster from scratch')
            ->addOption('verify', null, InputOption::VALUE_NONE, 'Run semantic verification on existing clusters, remove outliers')
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Time window for rebuild (e.g., "14d", "48h")', '14d');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $rebuild = $input->getOption('rebuild');
        $verify = $input->getOption('verify');
        $sinceStr = $input->getOption('since');

        if ($dryRun) {
            $io->note('DRY RUN mode — no changes will be persisted');
        }

        if ($rebuild) {
            return $this->handleRebuild($io, $sinceStr, $dryRun);
        }

        if ($verify) {
            return $this->handleVerify($io, $dryRun);
        }

        $io->warning('No action specified. Use --rebuild or --verify.');
        $io->listing([
            '--rebuild     Wipe all clusters and re-cluster from scratch',
            '--verify      Run semantic verification on existing clusters',
            '--dry-run     Preview without persisting changes',
            '--since=14d   Time window for rebuild',
        ]);

        return Command::SUCCESS;
    }

    private function handleRebuild(SymfonyStyle $io, string $sinceStr, bool $dryRun): int
    {
        $io->title('Cluster Rebuild');
        $since = $this->parseSince($sinceStr);
        $io->writeln(sprintf('Time window: since %s', $since->format('Y-m-d H:i:s')));

        // Show current state
        $conn = $this->em->getConnection();
        $currentCount = (int) $conn->fetchOne('SELECT COUNT(*) FROM story_clusters');
        $io->writeln(sprintf('Current clusters: %d', $currentCount));

        if (!$dryRun) {
            $io->section('Wiping existing clusters...');

            $conn->executeStatement('DELETE FROM story_cluster_press_release');
            $conn->executeStatement('DELETE FROM story_cluster_topic');
            $conn->executeStatement('DELETE FROM story_clusters');

            $io->writeln('All clusters deleted.');

            // Recreate ES index with latest analyzer settings
            $io->section('Recreating ES index...');
            $this->indexManager->createIndex(deleteIfExists: true);
            $io->writeln('ES index recreated with updated analyzer.');

            // Re-cluster
            $io->section('Running clustering...');
            $processed = $this->clusteringService->clusterNewPressReleases($since, false);
            $io->writeln(sprintf('Processed: %d PressReleases', $processed));

            // Score all clusters
            $io->section('Scoring clusters...');
            $clusters = $this->clusterRepository->findAll();
            foreach ($clusters as $cluster) {
                $score = $this->calculator->calculate($cluster);
                $cluster->setImportanceScore($score);
            }
            $this->em->flush();
            $io->writeln(sprintf('Scored: %d clusters', \count($clusters)));

            // Report
            $this->reportStats($io);

            $io->success('Rebuild complete');
        } else {
            $io->note(sprintf('DRY RUN: would delete %d clusters and re-cluster all PRs since %s', $currentCount, $since->format('Y-m-d')));
        }

        return Command::SUCCESS;
    }

    private function handleVerify(SymfonyStyle $io, bool $dryRun): int
    {
        $io->title('Cluster Semantic Verification');

        if (!$this->verifier->isEnabled()) {
            $io->warning('Semantic verification is disabled in AppSettings. Enabling temporarily for this run.');
        }

        $clusters = $this->clusterRepository->findBy([], ['articleCount' => 'DESC']);
        $totalRemoved = 0;
        $clustersChecked = 0;

        foreach ($clusters as $cluster) {
            $prs = $cluster->getPressReleases()->toArray();
            if (\count($prs) <= 1) {
                continue;
            }

            $clustersChecked++;
            $headline = $cluster->getPrimaryHeadline();
            $minConfidence = $this->appSettings->getFloat('cluster_semantic_min_confidence', 0.70);

            foreach ($prs as $pr) {
                $result = $this->verifier->verify($pr->getTitle(), $headline);

                if (!$result->sameStory || $result->confidence < $minConfidence) {
                    $io->writeln(sprintf(
                        '  [REMOVE] Cluster #%d "%s" ← PR #%d "%s" (confidence: %.2f, reason: %s)',
                        $cluster->getId(),
                        mb_substr($headline, 0, 50),
                        $pr->getId(),
                        mb_substr($pr->getTitle(), 0, 50),
                        $result->confidence,
                        $result->reason,
                    ));

                    if (!$dryRun) {
                        $cluster->removePressRelease($pr);
                    }
                    $totalRemoved++;
                }
            }

            if (!$dryRun) {
                $cluster->recalculateCounts();
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->success(sprintf(
            '%s: checked %d clusters, removed %d outlier PRs',
            $dryRun ? 'DRY RUN' : 'Done',
            $clustersChecked,
            $totalRemoved,
        ));

        return Command::SUCCESS;
    }

    private function reportStats(SymfonyStyle $io): void
    {
        $conn = $this->em->getConnection();
        $stats = $conn->fetchAssociative('
            SELECT
                COUNT(*) as total,
                ROUND(AVG(article_count)::numeric, 1) as avg_prs,
                MAX(article_count) as max_prs,
                ROUND(AVG(source_count)::numeric, 1) as avg_sources,
                ROUND(AVG(importance_score)::numeric, 3) as avg_score,
                ROUND(MAX(importance_score)::numeric, 3) as max_score
            FROM story_clusters
        ');

        if ($stats) {
            $io->section('Cluster Statistics');
            $io->table(
                ['Metric', 'Value'],
                [
                    ['Total clusters', $stats['total']],
                    ['Avg PRs/cluster', $stats['avg_prs']],
                    ['Max PRs/cluster', $stats['max_prs']],
                    ['Avg sources/cluster', $stats['avg_sources']],
                    ['Avg importance score', $stats['avg_score']],
                    ['Max importance score', $stats['max_score']],
                ],
            );
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

        return new \DateTimeImmutable('-14 days');
    }
}
