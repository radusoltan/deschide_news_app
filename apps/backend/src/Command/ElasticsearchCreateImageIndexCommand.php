<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\ImageElasticService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:elasticsearch:create-image-index',
    description: 'Create Elasticsearch index for images'
)]
class ElasticsearchCreateImageIndexCommand extends Command
{
    public function __construct(
        private readonly ImageElasticService $imageElasticService
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!$this->imageElasticService->isEnabled()) {
            $io->error('Elasticsearch is not enabled. Check your configuration.');
            return Command::FAILURE;
        }

        $io->title('Creating Elasticsearch Index for Images');

        try {
            $this->imageElasticService->createIndex();
            $io->success('Image index created successfully!');

            // Show cluster health
            $health = $this->imageElasticService->getHealth();
            $io->section('Cluster Health');
            $io->table(
                ['Property', 'Value'],
                [
                    ['Status', $health['status']],
                    ['Cluster Name', $health['cluster_name']],
                    ['Nodes', $health['number_of_nodes']],
                ]
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Failed to create index: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
