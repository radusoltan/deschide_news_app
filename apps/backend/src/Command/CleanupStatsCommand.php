<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\PageViewRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:stats:cleanup',
    description: 'Cleanup old statistics data'
)]
class CleanupStatsCommand extends Command
{
    public function __construct(
        private readonly PageViewRepository $pageViewRepository,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', null, InputOption::VALUE_OPTIONAL, 'Delete page_views older than N days', '90')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation prompt')
            ->addOption('archive', null, InputOption::VALUE_NONE, 'Archive data before deletion');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');
        $force = $input->getOption('force');
        $archive = $input->getOption('archive');

        $cutoffDate = new DateTime("-{$days} days");
        $io->title("Cleaning up page_views older than {$cutoffDate->format('Y-m-d')}");

        try {
            // Count records to delete
            $count = $this->pageViewRepository->countViewsOlderThan($cutoffDate);

            if ($count === 0) {
                $io->success('No old records to cleanup');

                return Command::SUCCESS;
            }

            $io->warning("Found {$count} records to delete");

            // Confirmation
            if (!$force) {
                $helper = $this->getHelper('question');
                $question = new ConfirmationQuestion('Continue with deletion? (yes/no) ', false);

                if (!$helper->ask($input, $output, $question)) {
                    $io->note('Operation cancelled');

                    return Command::SUCCESS;
                }
            }

            // Archive if requested
            if ($archive) {
                $io->section('Archiving data...');
                $this->archivePageViews($cutoffDate, $io);
            }

            // Delete old records
            $io->section('Deleting old records...');
            $deleted = $this->pageViewRepository->deleteOlderThan($cutoffDate);

            $io->success("Deleted {$deleted} old page_view records");

            $this->logger->info('Page views cleaned up', [
                'cutoff_date' => $cutoffDate->format('Y-m-d'),
                'deleted_count' => $deleted,
                'archived' => $archive,
            ]);

            // Note about aggregated stats
            $io->note('Aggregated stats (article_stats_daily, site_stats_daily) were preserved');

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->logger->error('Stats cleanup failed: ' . $e->getMessage(), [
                'exception' => $e,
                'command' => $this->getName(),
            ]);
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }

    private function archivePageViews(DateTime $cutoffDate, SymfonyStyle $io): void
    {
        $archiveDir = \dirname(__DIR__, 2) . '/var/archives';
        if (!is_dir($archiveDir)) {
            mkdir($archiveDir, 0o777, true);
        }

        $filename = $archiveDir . '/page_views_' . $cutoffDate->format('Y-m-d') . '.csv';
        $handle = fopen($filename, 'w');

        // Write CSV header
        fputcsv($handle, ['id', 'article_id', 'visitor_id', 'ip_address', 'user_agent', 'referrer', 'viewed_at', 'session_duration']);

        // Fetch and write data in batches
        $pageViews = $this->pageViewRepository->findViewsOlderThan($cutoffDate);
        $count = 0;

        foreach ($pageViews as $view) {
            fputcsv($handle, [
                $view->getId(),
                $view->getArticle()?->getId(),
                $view->getVisitorId(),
                $view->getIpAddress(),
                $view->getUserAgent(),
                $view->getReferrer(),
                $view->getViewedAt()->format('Y-m-d H:i:s'),
                $view->getSessionDuration(),
            ]);
            ++$count;
        }

        fclose($handle);
        $io->success("Archived {$count} records to: {$filename}");
    }
}
