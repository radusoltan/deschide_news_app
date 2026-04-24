<?php

declare(strict_types=1);

namespace App\Command\Editorial;

use App\Entity\Article;
use App\Message\Editorial\IngestArticleMessage;
use App\Service\Editorial\ArticleIngestionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:editorial:ingest-article',
    description: 'Run AI ingestion on an article (entity extraction, connection detection)',
)]
final class IngestArticleCommand extends Command
{
    public function __construct(
        private readonly ArticleIngestionService $ingestionService,
        private readonly EntityManagerInterface $em,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('article-id', 'a', InputOption::VALUE_REQUIRED, 'Article ID to ingest')
            ->addOption('async', null, InputOption::VALUE_NONE, 'Dispatch via messenger instead of synchronous')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without writing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $articleId = (int) $input->getOption('article-id');

        if ($articleId <= 0) {
            $io->error('Please provide --article-id=N');

            return Command::FAILURE;
        }

        $article = $this->em->getRepository(Article::class)->find($articleId);
        if ($article === null) {
            $io->error("Article #{$articleId} not found");

            return Command::FAILURE;
        }

        $io->title("Ingesting article #{$articleId}: " . mb_substr($article->getTitle() ?? '', 0, 60));

        if ($input->getOption('async')) {
            $this->messageBus->dispatch(new IngestArticleMessage($articleId));
            $io->success('IngestArticleMessage dispatched to editorial queue');

            return Command::SUCCESS;
        }

        try {
        // Synchronous execution
        $io->section('Step 1: Entity extraction');
        $entities = $this->ingestionService->extractEntities($article);

        if (!$entities->hasEntities()) {
            $io->warning('No entities extracted');

            return Command::SUCCESS;
        }

        $io->table(
            ['Type', 'Count', 'Items'],
            [
                ['Persons', \count($entities->persons), implode(', ', array_column($entities->persons, 'name'))],
                ['Institutions', \count($entities->institutions), implode(', ', array_column($entities->institutions, 'name'))],
                ['Events', \count($entities->events), implode(', ', array_column($entities->events, 'name'))],
                ['Locations', \count($entities->locations), implode(', ', array_column($entities->locations, 'name'))],
                ['Topics', \count($entities->topics), implode(', ', $entities->topics)],
            ],
        );

        $io->info("Confidence: {$entities->confidence}");

        if ($input->getOption('dry-run')) {
            $io->note('Dry run — no changes written');

            return Command::SUCCESS;
        }

        $io->section('Step 2: NotebookLM feed');
        $fed = $this->ingestionService->feedNotebookLM($article);
        $io->info($fed ? 'Article fed to NotebookLM' : 'NotebookLM skipped (unavailable or no mapping)');

        $io->success("Ingestion complete: {$entities->totalCount()} entities extracted");

        return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Article ingestion failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
