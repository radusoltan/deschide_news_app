<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:press-release:dedup-urls',
    description: 'Remove duplicate PressReleases that share the same source_url, keeping the oldest',
)]
class PressReleaseDedupUrlsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Report what would be removed without persisting');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $conn = $this->em->getConnection();

        $io->title('PressRelease Source URL Deduplication');

        if ($dryRun) {
            $io->note('DRY RUN — no changes will be persisted');
        }

        $duplicateGroups = $conn->fetchAllAssociative('
            SELECT source_url, COUNT(*) as cnt, MIN(id) as keep_id
            FROM press_releases
            WHERE source_url IS NOT NULL
            GROUP BY source_url
            HAVING COUNT(*) > 1
            ORDER BY COUNT(*) DESC
        ');

        if ($duplicateGroups === []) {
            $io->success('No duplicates found.');
            return Command::SUCCESS;
        }

        $totalDuplicates = 0;
        $totalRemoved = 0;

        foreach ($duplicateGroups as $group) {
            $sourceUrl = $group['source_url'];
            $keepId = (int) $group['keep_id'];
            $count = (int) $group['cnt'];
            $duplicateCount = $count - 1;
            $totalDuplicates += $duplicateCount;

            $duplicateIds = $conn->fetchFirstColumn(
                'SELECT id FROM press_releases WHERE source_url = ? AND id != ? ORDER BY id',
                [$sourceUrl, $keepId],
            );

            $io->writeln(sprintf(
                '  URL: %s — keeping #%d, removing %d duplicates: [%s]',
                mb_substr($sourceUrl, 0, 80),
                $keepId,
                $duplicateCount,
                implode(', ', $duplicateIds),
            ));

            if (!$dryRun) {
                $idList = implode(',', array_map('intval', $duplicateIds));

                $conn->executeStatement(
                    "DELETE FROM press_releases WHERE id IN ($idList)"
                );

                $totalRemoved += $duplicateCount;
            }
        }

        if (!$dryRun) {
            $io->newLine();
            $io->success(sprintf(
                'Removed %d duplicates from %d unique URLs.',
                $totalRemoved,
                \count($duplicateGroups),
            ));
        } else {
            $io->newLine();
            $io->success(sprintf(
                'DRY RUN: would remove %d duplicates from %d unique URLs.',
                $totalDuplicates,
                \count($duplicateGroups),
            ));
        }

        return Command::SUCCESS;
    }
}
