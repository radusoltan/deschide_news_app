<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\PressReleaseRepository;
use App\Service\Clustering\PressReleaseIndexer;
use App\Service\Clustering\PressReleaseIndexManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:elasticsearch:index-press-releases',
    description: 'Create and populate the PressRelease Elasticsearch index for clustering',
)]
class ElasticsearchIndexPressReleasesCommand extends Command
{
    public function __construct(
        private readonly PressReleaseIndexManager $indexManager,
        private readonly PressReleaseIndexer $indexer,
        private readonly PressReleaseRepository $pressReleaseRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('recreate', null, InputOption::VALUE_NONE, 'Drop and recreate the index before indexing')
            ->addOption('since', null, InputOption::VALUE_REQUIRED, 'Only index PRs created since (e.g., "7d", "24h")')
            ->addOption('batch-size', 'b', InputOption::VALUE_REQUIRED, 'Bulk batch size', '200');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->indexManager->isEnabled()) {
            $io->error('Elasticsearch is not enabled. Check ELASTICSEARCH_HOST in .env.');
            return Command::FAILURE;
        }

        $recreate = $input->getOption('recreate');
        $sinceStr = $input->getOption('since');
        $batchSize = (int) $input->getOption('batch-size');

        $io->title('PressRelease Elasticsearch Index');

        // Create/recreate index
        $this->indexManager->createIndex(deleteIfExists: $recreate);
        $io->writeln($recreate ? 'Index recreated.' : 'Index ready.');

        // Fetch PressReleases
        $since = $sinceStr !== null ? $this->parseSince($sinceStr) : null;

        $qb = $this->pressReleaseRepository->createQueryBuilder('pr')
            ->orderBy('pr.createdAt', 'ASC');

        if ($since !== null) {
            $qb->where('pr.createdAt >= :since')
                ->setParameter('since', $since);
            $io->writeln(sprintf('Indexing PRs since %s', $since->format('Y-m-d H:i:s')));
        } else {
            $io->writeln('Indexing ALL PressReleases');
        }

        $pressReleases = $qb->getQuery()->toIterable();

        $indexed = $this->indexer->bulkIndex($pressReleases, $batchSize);

        $io->success(sprintf('Indexed %d PressReleases into %s', $indexed, PressReleaseIndexManager::INDEX_NAME));

        return Command::SUCCESS;
    }

    private function parseSince(string $since): \DateTimeImmutable
    {
        if (preg_match('/^(\d+)h$/', $since, $m)) {
            return new \DateTimeImmutable(sprintf('-%d hours', (int) $m[1]));
        }
        if (preg_match('/^(\d+)d$/', $since, $m)) {
            return new \DateTimeImmutable(sprintf('-%d days', (int) $m[1]));
        }

        return new \DateTimeImmutable('-7 days');
    }
}
