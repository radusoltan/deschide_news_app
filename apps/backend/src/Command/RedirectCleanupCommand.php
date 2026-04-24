<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UrlRedirectRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:redirects:cleanup',
    description: 'Clean up unused and old redirects',
)]
class RedirectCleanupCommand extends Command
{
    public function __construct(
        private UrlRedirectRepository $redirectRepository,
        private EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate cleanup without deleting')
            ->addOption('older-than', null, InputOption::VALUE_REQUIRED, 'Delete redirects older than N days (default: 180)', '180')
            ->addOption('max-hits', null, InputOption::VALUE_REQUIRED, 'Only delete redirects with hits <= N (default: 0)', '0')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Filter by redirect type (article, category, author, manual)')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Force deletion without confirmation')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $olderThan = (int) $input->getOption('older-than');
        $maxHits = (int) $input->getOption('max-hits');
        $type = $input->getOption('type');
        $force = $input->getOption('force');

        $io->title('Redirect Cleanup');

        // Display configuration
        $io->section('Configuration');
        $io->table(
            ['Option', 'Value'],
            [
                ['Dry Run', $dryRun ? 'Yes' : 'No'],
                ['Older Than', $olderThan . ' days'],
                ['Max Hits', $maxHits],
                ['Type Filter', $type ?: 'All types'],
                ['Force', $force ? 'Yes' : 'No'],
            ]
        );

        try {
        // Find candidates for deletion
        $io->section('Finding Redirects to Delete');

        $cutoffDate = new DateTimeImmutable("-{$olderThan} days");

        $qb = $this->redirectRepository->createQueryBuilder('r')
            ->where('r.createdAt < :cutoffDate')
            ->andWhere('r.hitCount <= :maxHits')
            ->setParameter('cutoffDate', $cutoffDate)
            ->setParameter('maxHits', $maxHits)
            ->orderBy('r.createdAt', 'ASC');

        if ($type) {
            $qb->andWhere('r.type = :type')
                ->setParameter('type', $type);
        }

        $redirectsToDelete = $qb->getQuery()->getResult();
        $count = \count($redirectsToDelete);

        if ($count === 0) {
            $io->success('No redirects found matching the criteria.');

            return Command::SUCCESS;
        }

        $io->text(\sprintf('Found %d redirects matching deletion criteria', $count));

        // Display sample redirects
        $io->section('Sample Redirects (first 10)');
        $sampleRedirects = \array_slice($redirectsToDelete, 0, 10);
        $table = [];
        foreach ($sampleRedirects as $redirect) {
            $table[] = [
                $redirect->getId(),
                substr($redirect->getOldUrl(), 0, 40),
                substr($redirect->getNewUrl(), 0, 40),
                $redirect->getType(),
                $redirect->getHitCount(),
                $redirect->getCreatedAt()->format('Y-m-d'),
            ];
        }
        $io->table(['ID', 'Old URL', 'New URL', 'Type', 'Hits', 'Created'], $table);

        if ($count > 10) {
            $io->text(\sprintf('... and %d more redirects', $count - 10));
        }

        // Statistics by type
        $io->section('Deletion Statistics by Type');
        $statsByType = [];
        foreach ($redirectsToDelete as $redirect) {
            $redirectType = $redirect->getType();
            if (!isset($statsByType[$redirectType])) {
                $statsByType[$redirectType] = ['count' => 0, 'total_hits' => 0];
            }
            ++$statsByType[$redirectType]['count'];
            $statsByType[$redirectType]['total_hits'] += $redirect->getHitCount();
        }

        $statsTable = [];
        foreach ($statsByType as $redirectType => $stats) {
            $statsTable[] = [
                $redirectType,
                $stats['count'],
                $stats['total_hits'],
            ];
        }
        $io->table(['Type', 'Count', 'Total Hits'], $statsTable);

        // Dry run mode
        if ($dryRun) {
            $io->warning('DRY RUN MODE: No redirects will be deleted');
            $io->note(\sprintf('Would delete %d redirects', $count));

            return Command::SUCCESS;
        }

        // Confirmation
        if (!$force) {
            $confirm = $io->confirm(
                \sprintf('Are you sure you want to delete %d redirects?', $count),
                false
            );

            if (!$confirm) {
                $io->warning('Cleanup cancelled by user');

                return Command::SUCCESS;
            }
        }

        // Delete redirects
        $io->section('Deleting Redirects');
        $io->progressStart($count);

        $deletedCount = 0;
        $batchSize = 100;

        foreach ($redirectsToDelete as $i => $redirect) {
            $this->entityManager->remove($redirect);
            ++$deletedCount;

            // Flush in batches
            if (($i + 1) % $batchSize === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear();
            }

            $io->progressAdvance();
        }

        // Final flush
        $this->entityManager->flush();
        $this->entityManager->clear();

        $io->progressFinish();

        // Summary
        $io->newLine(2);
        $io->success(\sprintf('Successfully deleted %d redirects', $deletedCount));

        // Display deletion summary
        $io->table(
            ['Metric', 'Value'],
            [
                ['Total Deleted', $deletedCount],
                ['Older Than', $cutoffDate->format('Y-m-d H:i:s')],
                ['Max Hits', $maxHits],
                ['Type Filter', $type ?: 'All'],
            ]
        );

        return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Redirect cleanup failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
