<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ElasticService;
use Exception;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:elasticsearch:create-index',
    description: 'Create Elasticsearch articles indices with mappings for all locales',
)]
class ElasticsearchCreateIndexCommand extends Command
{
    public function __construct(
        private readonly ElasticService $elasticService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('locale', 'l', InputOption::VALUE_OPTIONAL, 'Create index for specific locale only', null);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $locale = $input->getOption('locale');

        if (!$this->elasticService->isEnabled()) {
            $io->warning('Elasticsearch is disabled. Check ELASTICSEARCH_HOST configuration.');

            return Command::SUCCESS;
        }

        try {
            if ($locale) {
                $io->info(\sprintf('Creating Elasticsearch index for locale: %s', $locale));
                $this->elasticService->createIndex($locale);
                $io->success(\sprintf('Index for locale "%s" created successfully!', $locale));
            } else {
                $io->info('Creating Elasticsearch indices for all locales (ro, en, ru)...');
                $this->elasticService->createAllIndices();
                $io->success('All indices created successfully!');
            }

            // Show cluster health
            $health = $this->elasticService->getClusterHealth();
            if ($health) {
                $io->table(
                    ['Cluster', 'Status', 'Nodes'],
                    [[
                        $health['cluster_name'] ?? 'N/A',
                        $health['status'] ?? 'N/A',
                        $health['number_of_nodes'] ?? 'N/A',
                    ]]
                );
            }

            return Command::SUCCESS;
        } catch (Exception $e) {
            $io->error('Failed to create index: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }
}
