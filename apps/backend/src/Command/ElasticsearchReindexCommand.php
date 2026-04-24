<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Article;
use App\Enum\ArticleStatus;
use App\Service\Search\ArticleIndexer;
use App\Service\Search\ElasticsearchIndexManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:search:reindex',
    description: 'Recreează indexul Elasticsearch trilingv și reindexează toate articolele',
)]
final class ElasticsearchReindexCommand extends Command
{
    public function __construct(
        private readonly ElasticsearchIndexManager $indexManager,
        private readonly ArticleIndexer $indexer,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Delete existing index and recreate')
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Batch size for indexing', '100')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->indexManager->isEnabled()) {
            $io->error('Elasticsearch is not configured or disabled.');

            return Command::FAILURE;
        }

        $force = $input->getOption('force');
        $batchSize = (int) $input->getOption('batch-size');

        try {
            // Create/recreate index
            $io->section('Creating trilingual index...');
            $this->indexManager->createIndex(deleteIfExists: $force);
            $io->writeln('Index: <info>' . $this->indexManager->getIndexName() . '</info>');

            // Count articles
            $repo = $this->em->getRepository(Article::class);
            $total = $repo->count(['status' => ArticleStatus::PUBLISHED]);
            $io->writeln("Articles to index: <info>{$total}</info>");

            if ($total === 0) {
                $io->success('No published articles to index.');

                return Command::SUCCESS;
            }

            // Index in batches
            $io->section('Indexing articles...');
            $io->progressStart($total);

            $offset = 0;
            $indexed = 0;

            while ($offset < $total) {
                $articles = $repo->findBy(
                    ['status' => ArticleStatus::PUBLISHED],
                    ['id' => 'ASC'],
                    $batchSize,
                    $offset,
                );

                foreach ($articles as $article) {
                    $this->indexer->index($article);
                    $indexed++;
                    $io->progressAdvance();
                }

                // Clear entity manager to free memory
                $this->em->clear();
                $offset += $batchSize;
            }

            $io->progressFinish();
            $io->success("Reindexed {$indexed} articles into trilingual index.");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $io->error('Elasticsearch reindex failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
