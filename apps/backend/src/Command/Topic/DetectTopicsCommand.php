<?php

declare(strict_types=1);

namespace App\Command\Topic;

use App\Repository\PressReleaseRepository;
use App\Service\Topic\PressReleaseTopicDetector;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:topic:detect',
    description: 'Backfill topic detection for press releases',
)]
final class DetectTopicsCommand extends Command
{
    public function __construct(
        private readonly PressReleaseRepository $pressReleaseRepository,
        private readonly PressReleaseTopicDetector $detector,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Process PRs from the last N days (e.g. 30d)', '30d')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Re-detect even if PR already has topic tags')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview without persisting')
            ->addOption('batch-size', null, InputOption::VALUE_REQUIRED, 'Flush every N press releases', '100')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sinceStr = $input->getOption('since');
        $force = $input->getOption('force');
        $dryRun = $input->getOption('dry-run');
        $batchSize = (int) $input->getOption('batch-size');

        // Parse "30d" → DateTimeImmutable
        $days = (int) rtrim($sinceStr, 'd');
        if ($days <= 0) {
            $io->error('Invalid --since value. Use format like "30d".');

            return Command::FAILURE;
        }

        $since = new \DateTimeImmutable("-{$days} days");
        $io->title('Topic Detection Backfill');
        $io->text(sprintf('Processing PRs since %s (%dd ago)', $since->format('Y-m-d'), $days));

        if ($dryRun) {
            $io->note('DRY RUN — no changes will be persisted');
        }

        // Query PRs: either untagged or all (if --force)
        $qb = $this->em->createQueryBuilder()
            ->select('pr')
            ->from(\App\Entity\PressRelease::class, 'pr')
            ->where('pr.receivedAt >= :since')
            ->setParameter('since', $since)
            ->orderBy('pr.receivedAt', 'ASC');

        if (!$force) {
            $qb->leftJoin('pr.pressReleaseTopics', 'prt')
               ->andWhere('prt.id IS NULL');
        }

        $pressReleases = $qb->getQuery()->getResult();
        $total = \count($pressReleases);

        if ($total === 0) {
            $io->success('No press releases to process.');

            return Command::SUCCESS;
        }

        $io->text(sprintf('Found %d press releases to process', $total));

        $progress = new ProgressBar($output, $total);
        $progress->setFormat(' %current%/%max% [%bar%] %percent:3s%% %elapsed:6s%/%estimated:-6s% %memory:6s%');
        $progress->start();

        $tagged = 0;
        $skipped = 0;
        $processed = 0;

        foreach ($pressReleases as $pr) {
            $results = $this->detector->detect($pr, useGeminiFallback: false);

            if ($results !== []) {
                if (!$dryRun) {
                    $this->detector->detectAndPersist($pr, useGeminiFallback: false);
                }
                $tagged++;
            } else {
                $skipped++;
            }

            $processed++;

            if (!$dryRun && $processed % $batchSize === 0) {
                $this->em->clear();
            }

            $progress->advance();
        }

        $progress->finish();
        $io->newLine(2);

        $io->success(sprintf(
            '%s%d/%d press releases tagged with topics (%d skipped, no match)',
            $dryRun ? '[DRY RUN] ' : '',
            $tagged,
            $total,
            $skipped,
        ));

        return Command::SUCCESS;
    }
}
