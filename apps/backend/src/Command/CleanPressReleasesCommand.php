<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\PressReleaseRepository;
use App\Service\Cleaning\SourceContentCleanerRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:clean-press-releases',
    description: 'Apply source-specific content cleaning to existing PressReleases',
)]
class CleanPressReleasesCommand extends Command
{
    public function __construct(
        private readonly PressReleaseRepository $pressReleaseRepository,
        private readonly SourceContentCleanerRegistry $cleanerRegistry,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('all', null, InputOption::VALUE_NONE, 'Clean all press releases')
            ->addOption('source', 's', InputOption::VALUE_REQUIRED, 'Filter by source name (partial match)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview changes without persisting')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Maximum number of PRs to process', '0')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = $input->getOption('dry-run');
        $sourceFilter = $input->getOption('source');
        $all = $input->getOption('all');
        $limit = (int) $input->getOption('limit');

        if (!$all && $sourceFilter === null) {
            $io->error('Specify --all or --source=<name>');

            return Command::INVALID;
        }

        $io->title('Retroactive Content Cleaning');

        // Build query
        $qb = $this->pressReleaseRepository->createQueryBuilder('pr')
            ->orderBy('pr.id', 'ASC');

        if ($sourceFilter !== null) {
            $qb->where('pr.sourceName LIKE :source')
                ->setParameter('source', '%' . $sourceFilter . '%');
        }

        if ($limit > 0) {
            $qb->setMaxResults($limit);
        }

        $pressReleases = $qb->getQuery()->getResult();
        $total = \count($pressReleases);

        if ($total === 0) {
            $io->success('No press releases found matching criteria.');

            return Command::SUCCESS;
        }

        $io->writeln(sprintf('Processing %d press releases%s...', $total, $dryRun ? ' (DRY RUN)' : ''));

        $cleaned = 0;
        $unchanged = 0;
        $totalReduced = 0;

        foreach ($pressReleases as $pr) {
            $sourceName = $pr->getSourceName() ?? '';
            $originalContent = $pr->getContent();
            $originalLength = mb_strlen(strip_tags($originalContent));

            $cleanedContent = $this->cleanerRegistry->clean($sourceName, $originalContent);
            $cleanedLength = mb_strlen(strip_tags($cleanedContent));

            if ($originalContent === $cleanedContent) {
                $unchanged++;

                continue;
            }

            $reduction = $originalLength > 0
                ? round(100 * (1 - $cleanedLength / $originalLength), 1)
                : 0;
            $totalReduced += ($originalLength - $cleanedLength);

            $io->writeln(sprintf(
                '  #%d [%s]: %d → %d chars (-%s%%)',
                $pr->getId(),
                $sourceName,
                $originalLength,
                $cleanedLength,
                $reduction,
            ));

            if (!$dryRun) {
                $pr->setContent($cleanedContent);
            }

            $cleaned++;
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->writeln('');
        $io->table(
            ['Metric', 'Value'],
            [
                ['Total processed', (string) $total],
                ['Cleaned', (string) $cleaned],
                ['Unchanged', (string) $unchanged],
                ['Total chars removed', number_format($totalReduced)],
                ['Mode', $dryRun ? 'DRY RUN' : 'APPLIED'],
            ],
        );

        $io->success(sprintf('Done. %d press releases cleaned.', $cleaned));

        return Command::SUCCESS;
    }
}
