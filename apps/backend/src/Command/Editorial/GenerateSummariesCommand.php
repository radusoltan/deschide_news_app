<?php

declare(strict_types=1);

namespace App\Command\Editorial;

use App\Entity\Article;
use App\Service\Editorial\InternalSummaryService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:editorial:generate-summaries',
    description: 'Generează rezumate interne (TL;DR) pentru articolele fără summary',
)]
final class GenerateSummariesCommand extends Command
{
    public function __construct(
        private readonly InternalSummaryService $summaryService,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('batch', 'b', InputOption::VALUE_REQUIRED, 'Numărul maxim de articole de procesat', '50')
            ->addOption('article-id', null, InputOption::VALUE_REQUIRED, 'ID-ul unui articol specific')
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Procesează doar articolele create după această dată (YYYY-MM-DD)')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulează fără a salva în DB');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Generare rezumate interne (TL;DR)');

        $dryRun = $input->getOption('dry-run');
        $articleId = $input->getOption('article-id');

        if ($articleId !== null) {
            return $this->processSingleArticle((int) $articleId, $dryRun, $io);
        }

        return $this->processBatch($input, $dryRun, $io);
    }

    private function processSingleArticle(int $articleId, bool $dryRun, SymfonyStyle $io): int
    {
        try {
            $article = $this->em->getRepository(Article::class)->find($articleId);

            if ($article === null) {
                $io->error("Articolul cu ID {$articleId} nu a fost găsit.");

                return Command::FAILURE;
            }

            $result = $this->generateForArticle($article, $dryRun, $io);

            return $result ? Command::SUCCESS : Command::FAILURE;
        } catch (\Throwable $e) {
            $io->error('Summary generation failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function processBatch(InputInterface $input, bool $dryRun, SymfonyStyle $io): int
    {
        try {
        $batch = (int) $input->getOption('batch');
        $since = $input->getOption('since');

        $qb = $this->em->getRepository(Article::class)->createQueryBuilder('a')
            ->where('a.internalSummary IS NULL')
            ->andWhere('a.content IS NOT NULL')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($batch);

        if ($since !== null) {
            $sinceDate = new \DateTimeImmutable($since);
            $qb->andWhere('a.createdAt >= :since')
                ->setParameter('since', $sinceDate);
        }

        $articles = $qb->getQuery()->getResult();
        $total = \count($articles);

        if ($total === 0) {
            $io->success('Nu există articole fără summary.');

            return Command::SUCCESS;
        }

        $io->info("Procesare {$total} articole" . ($dryRun ? ' (DRY RUN)' : '') . '...');
        $io->progressStart($total);

        $generated = 0;
        $skipped = 0;

        foreach ($articles as $article) {
            if ($this->generateForArticle($article, $dryRun, $io, silent: true)) {
                $generated++;
            } else {
                $skipped++;
            }

            $io->progressAdvance();

            // Flush every 10 articles
            if (!$dryRun && $generated % 10 === 0) {
                $this->em->flush();
            }
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->progressFinish();
        $io->success("Completat: {$generated} generate, {$skipped} sărite.");

        return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Batch summary generation failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function generateForArticle(Article $article, bool $dryRun, SymfonyStyle $io, bool $silent = false): bool
    {
        $bodyText = strip_tags($article->getContent() ?? '');
        $title = $article->getTitle() ?? '';

        $summary = $this->summaryService->generateSummary($title, $bodyText);

        if ($summary === null) {
            if (!$silent) {
                $io->warning("Nu s-a putut genera summary pentru articolul #{$article->getId()}");
            }

            return false;
        }

        if (!$dryRun) {
            $article->setInternalSummary($summary);
        }

        if (!$silent) {
            $io->section("Articol #{$article->getId()}: {$title}");
            $io->text($summary);

            if ($dryRun) {
                $io->note('DRY RUN — nu s-a salvat în DB');
            }
        }

        return true;
    }
}
