<?php

declare(strict_types=1);

namespace App\Command\Editorial;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use App\Message\Editorial\IngestArticleMessage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:editorial:batch-ingest',
    description: 'Dispatch AI ingestion (entity extraction, MOC, connections) for un-ingested articles',
)]
final class BatchIngestCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('batch', 'b', InputOption::VALUE_REQUIRED, 'Maximum articles to dispatch', '50')
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Only articles created after this date (YYYY-MM-DD)')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Re-ingest even if already ingested')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be dispatched without dispatching')
            ->addOption('delay', null, InputOption::VALUE_REQUIRED, 'Milliseconds delay between dispatches (rate limiting)', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Batch AI Ingestion — Dispatch IngestArticleMessage');

        $batch = (int) $input->getOption('batch');
        $since = $input->getOption('since');
        $force = (bool) $input->getOption('force');
        $dryRun = (bool) $input->getOption('dry-run');
        $delay = (int) $input->getOption('delay');

        if ($dryRun) {
            $io->warning('DRY-RUN: nimic nu se dispatch-ează.');
        }

        $qb = $this->em->getRepository(Article::class)->createQueryBuilder('a')
            ->where('a.content IS NOT NULL')
            ->andWhere('a.status = :status')
            ->setParameter('status', ArticleStatus::PUBLISHED)
            ->orderBy('a.publishedAt', 'DESC')
            ->setMaxResults($batch);

        if (!$force) {
            $qb->andWhere('a.ingestedAt IS NULL');
        }

        if ($since !== null) {
            $qb->andWhere('a.createdAt >= :since')
                ->setParameter('since', new \DateTimeImmutable($since));
        }

        /** @var Article[] $articles */
        $articles = $qb->getQuery()->getResult();

        if (\count($articles) === 0) {
            $io->success('Niciun articol de procesat.');

            return Command::SUCCESS;
        }

        $io->info(sprintf('Articole găsite: %d', \count($articles)));

        $dispatched = 0;

        $io->progressStart(\count($articles));

        foreach ($articles as $article) {
            if (!$dryRun) {
                $this->bus->dispatch(new IngestArticleMessage(articleId: $article->getId()));
            }

            ++$dispatched;
            $io->progressAdvance();

            if ($delay > 0 && !$dryRun) {
                usleep($delay * 1000);
            }
        }

        $io->progressFinish();

        $io->table(
            ['Metric', 'Valoare'],
            [
                ['Articole găsite', (string) \count($articles)],
                ['Dispatched', $dryRun ? '0 (dry-run)' : (string) $dispatched],
                ['Force', $force ? 'DA' : 'NU'],
                ['Delay (ms)', (string) $delay],
            ],
        );

        if ($dryRun) {
            $io->note('Rulează fără --dry-run pentru a dispatch-a mesajele.');
        } else {
            $io->success(sprintf(
                '%d mesaje IngestArticleMessage dispatched. Pornește worker: messenger:consume editorial -vv',
                $dispatched,
            ));
        }

        return Command::SUCCESS;
    }
}
