<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Article;
use App\Enum\ArchiveReason;
use App\Enum\ArticleStatus;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:archive-old-articles',
    description: 'Archive articles older than 4 years (published before 2021)'
)]
class ArchiveOldArticlesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'years',
                'y',
                InputOption::VALUE_OPTIONAL,
                'Number of years threshold (default: 4)',
                4
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Run in dry-run mode (no changes will be made)'
            )
            ->addOption(
                'batch-size',
                'b',
                InputOption::VALUE_OPTIONAL,
                'Number of articles to process in each batch (default: 100)',
                100
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $yearsThreshold = (int) $input->getOption('years');
        $dryRun = (bool) $input->getOption('dry-run');
        $batchSize = (int) $input->getOption('batch-size');

        // Calculate threshold date (e.g., 4 years ago)
        $thresholdDate = new DateTimeImmutable("-{$yearsThreshold} years");

        $io->title('Archive Old Articles');
        $io->info(\sprintf(
            'Archiving articles published before: %s (%d years ago)',
            $thresholdDate->format('Y-m-d'),
            $yearsThreshold
        ));

        if ($dryRun) {
            $io->warning('DRY-RUN MODE: No changes will be made');
        }

        try {
            // Find all published articles older than threshold
            $repository = $this->entityManager->getRepository(Article::class);
            $qb = $repository->createQueryBuilder('a')
                ->where('a.status = :published')
                ->andWhere('a.publishedAt < :threshold')
                ->setParameter('published', ArticleStatus::PUBLISHED)
                ->setParameter('threshold', $thresholdDate)
                ->orderBy('a.publishedAt', 'ASC');

            $query = $qb->getQuery();
            $articles = $query->getResult();

            $totalFound = \count($articles);

            if ($totalFound === 0) {
                $io->success('No articles found to archive.');

                return Command::SUCCESS;
            }

            $io->info(\sprintf('Found %d articles to archive', $totalFound));

            if (!$dryRun && !$io->confirm('Do you want to proceed with archiving?', false)) {
                $io->warning('Archiving cancelled by user.');

                return Command::SUCCESS;
            }

            $io->progressStart($totalFound);

            $archived = 0;
            $processed = 0;

            foreach ($articles as $article) {
                if ($dryRun) {
                    $io->text(\sprintf(
                        '[DRY-RUN] Would archive: ID=%d, Title=%s, Published=%s',
                        $article->getId(),
                        $article->getTitle(),
                        $article->getPublishedAt()->format('Y-m-d')
                    ));
                } else {
                    // Archive the article
                    $article->archive(ArchiveReason::OLD_CONTENT);
                    ++$archived;

                    // Flush in batches to avoid memory issues
                    ++$processed;
                    if ($processed % $batchSize === 0) {
                        $this->entityManager->flush();
                        $this->entityManager->clear(); // Clear the entity manager to free memory

                        // Re-fetch remaining articles (since we cleared the EM)
                        $qb = $repository->createQueryBuilder('a')
                            ->where('a.status = :published')
                            ->andWhere('a.publishedAt < :threshold')
                            ->setParameter('published', ArticleStatus::PUBLISHED)
                            ->setParameter('threshold', $thresholdDate)
                            ->orderBy('a.publishedAt', 'ASC');

                        $articles = $qb->getQuery()->getResult();

                        if (empty($articles)) {
                            break;
                        }
                    }
                }

                $io->progressAdvance();
            }

            // Final flush for remaining articles
            if (!$dryRun && $processed % $batchSize !== 0) {
                $this->entityManager->flush();
            }

            $io->progressFinish();

            if ($dryRun) {
                $io->success(\sprintf(
                    '[DRY-RUN] Would have archived %d articles published before %s',
                    $totalFound,
                    $thresholdDate->format('Y-m-d')
                ));
            } else {
                $io->success(\sprintf(
                    'Successfully archived %d articles published before %s',
                    $archived,
                    $thresholdDate->format('Y-m-d')
                ));
            }

            // Show statistics by year
            $io->section('Statistics by Publication Year');

            $qb = $repository->createQueryBuilder('a')
                ->select('YEAR(a.publishedAt) as year, COUNT(a.id) as count')
                ->where('a.status = :archived')
                ->setParameter('archived', ArticleStatus::ARCHIVED)
                ->groupBy('year')
                ->orderBy('year', 'ASC');

            $stats = $qb->getQuery()->getResult();

            if (!empty($stats)) {
                $table = [];
                foreach ($stats as $stat) {
                    $table[] = [$stat['year'], $stat['count']];
                }
                $io->table(['Year', 'Archived Articles'], $table);
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }
    }
}
