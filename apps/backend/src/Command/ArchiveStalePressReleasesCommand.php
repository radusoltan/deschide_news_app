<?php

declare(strict_types=1);

namespace App\Command;

use App\Enum\PressReleaseStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:press-release:archive-stale',
    description: 'Archive rejected press releases older than N days',
)]
final class ArchiveStalePressReleasesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('days', 'd', InputOption::VALUE_REQUIRED, 'Archive rejected PRs older than this many days', '30')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be archived without making changes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');
        $dryRun = $input->getOption('dry-run');
        $cutoff = new \DateTimeImmutable("-{$days} days");

        $io->title(sprintf('Archiving rejected press releases older than %d days (before %s)', $days, $cutoff->format('Y-m-d')));

        $count = (int) $this->em->createQuery(
            'SELECT COUNT(pr.id) FROM App\Entity\PressRelease pr
             WHERE pr.status = :status AND pr.createdAt < :cutoff'
        )
            ->setParameter('status', PressReleaseStatus::REJECTED)
            ->setParameter('cutoff', $cutoff)
            ->getSingleScalarResult();

        if ($count === 0) {
            $io->success('No stale press releases found.');

            return Command::SUCCESS;
        }

        $io->info(sprintf('Found %d rejected press releases older than %d days.', $count, $days));

        if ($dryRun) {
            $io->warning('Dry run — no changes made.');

            return Command::SUCCESS;
        }

        $updated = $this->em->createQuery(
            'UPDATE App\Entity\PressRelease pr
             SET pr.status = :archived
             WHERE pr.status = :rejected AND pr.createdAt < :cutoff'
        )
            ->setParameter('archived', PressReleaseStatus::ARCHIVED)
            ->setParameter('rejected', PressReleaseStatus::REJECTED)
            ->setParameter('cutoff', $cutoff)
            ->execute();

        $io->success(sprintf('Archived %d press releases older than %d days.', $updated, $days));

        return Command::SUCCESS;
    }
}
