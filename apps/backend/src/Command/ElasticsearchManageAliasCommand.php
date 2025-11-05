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
    name: 'app:elasticsearch:manage-alias',
    description: 'Manage Elasticsearch aliases and perform zero-downtime reindex'
)]
class ElasticsearchManageAliasCommand extends Command
{
    public function __construct(
        private readonly ElasticService $elasticService
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('action', 'a', InputOption::VALUE_REQUIRED, 'Action: list, create, swap, reindex')
            ->addOption('alias', null, InputOption::VALUE_OPTIONAL, 'Alias name')
            ->addOption('index', 'i', InputOption::VALUE_OPTIONAL, 'Index name')
            ->addOption('old-index', null, InputOption::VALUE_OPTIONAL, 'Old index for swap')
            ->addOption('new-index', null, InputOption::VALUE_OPTIONAL, 'New index for swap')
            ->addOption('source', 's', InputOption::VALUE_OPTIONAL, 'Source index for reindex')
            ->addOption('dest', 'd', InputOption::VALUE_OPTIONAL, 'Destination index for reindex');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $action = $input->getOption('action');

        if (!$this->elasticService->isEnabled()) {
            $io->warning('Elasticsearch is disabled.');

            return Command::SUCCESS;
        }

        try {
            return match ($action) {
                'list' => $this->listAliases($io, $input),
                'create' => $this->createAlias($io, $input),
                'swap' => $this->swapAlias($io, $input),
                'reindex' => $this->reindex($io, $input),
                default => $this->showUsage($io),
            };
        } catch (Exception $e) {
            $io->error('Operation failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    private function listAliases(SymfonyStyle $io, InputInterface $input): int
    {
        $indexName = $input->getOption('index');
        $aliases = $this->elasticService->getAliases($indexName);

        if (empty($aliases)) {
            $io->warning('No aliases found.');

            return Command::SUCCESS;
        }

        $io->title('Elasticsearch Aliases');

        foreach ($aliases as $index => $indexData) {
            if (isset($indexData['aliases']) && !empty($indexData['aliases'])) {
                $io->section("Index: $index");
                $rows = [];
                foreach ($indexData['aliases'] as $alias => $aliasData) {
                    $rows[] = [
                        $alias,
                        isset($aliasData['filter']) ? 'Yes' : 'No',
                        isset($aliasData['is_write_index']) && $aliasData['is_write_index'] ? 'Yes' : 'No',
                    ];
                }
                $io->table(['Alias', 'Filtered', 'Write Index'], $rows);
            }
        }

        return Command::SUCCESS;
    }

    private function createAlias(SymfonyStyle $io, InputInterface $input): int
    {
        $alias = $input->getOption('alias');
        $index = $input->getOption('index');

        if (!$alias || !$index) {
            $io->error('--alias and --index options are required');

            return Command::FAILURE;
        }

        $io->info(\sprintf('Creating alias "%s" for index "%s"...', $alias, $index));
        $this->elasticService->createAlias($alias, $index);
        $io->success('Alias created successfully!');

        return Command::SUCCESS;
    }

    private function swapAlias(SymfonyStyle $io, InputInterface $input): int
    {
        $alias = $input->getOption('alias');
        $oldIndex = $input->getOption('old-index');
        $newIndex = $input->getOption('new-index');

        if (!$alias || !$oldIndex || !$newIndex) {
            $io->error('--alias, --old-index, and --new-index options are required');

            return Command::FAILURE;
        }

        $io->warning('This will atomically swap the alias. Application downtime: ~0ms');
        $io->info(\sprintf('Alias: %s', $alias));
        $io->info(\sprintf('Remove from: %s', $oldIndex));
        $io->info(\sprintf('Add to: %s', $newIndex));

        if (!$io->confirm('Continue with alias swap?', false)) {
            $io->note('Operation cancelled');

            return Command::SUCCESS;
        }

        $this->elasticService->swapAlias($alias, $oldIndex, $newIndex);
        $io->success('Alias swapped successfully! Zero downtime achieved.');

        return Command::SUCCESS;
    }

    private function reindex(SymfonyStyle $io, InputInterface $input): int
    {
        $source = $input->getOption('source');
        $dest = $input->getOption('dest');

        if (!$source || !$dest) {
            $io->error('--source and --dest options are required');

            return Command::FAILURE;
        }

        $io->warning('This will copy all documents from source to destination index.');
        $io->info(\sprintf('Source: %s', $source));
        $io->info(\sprintf('Destination: %s', $dest));

        if (!$io->confirm('Continue with reindex?', false)) {
            $io->note('Operation cancelled');

            return Command::SUCCESS;
        }

        $io->text('Reindexing... This may take a while for large indices.');

        $result = $this->elasticService->reindex($source, $dest);

        $io->success('Reindex completed!');
        $io->table(
            ['Metric', 'Value'],
            [
                ['Total documents', $result['total'] ?? 0],
                ['Created', $result['created'] ?? 0],
                ['Updated', $result['updated'] ?? 0],
                ['Deleted', $result['deleted'] ?? 0],
                ['Took (ms)', $result['took'] ?? 0],
            ]
        );

        return Command::SUCCESS;
    }

    private function showUsage(SymfonyStyle $io): int
    {
        $io->title('Elasticsearch Alias Management');

        $io->section('Usage Examples');

        $io->text('<fg=yellow>1. List all aliases:</>');
        $io->text('   php bin/console app:elasticsearch:manage-alias --action=list');
        $io->newLine();

        $io->text('<fg=yellow>2. Create an alias:</>');
        $io->text('   php bin/console app:elasticsearch:manage-alias --action=create --alias=articles --index=deschide_articles_ro');
        $io->newLine();

        $io->text('<fg=yellow>3. Swap alias (zero-downtime):</>');
        $io->text('   php bin/console app:elasticsearch:manage-alias --action=swap --alias=articles --old-index=deschide_articles_ro --new-index=deschide_articles_ro_v2');
        $io->newLine();

        $io->text('<fg=yellow>4. Reindex documents:</>');
        $io->text('   php bin/console app:elasticsearch:manage-alias --action=reindex --source=old_index --dest=new_index');
        $io->newLine();

        $io->note('For zero-downtime reindex workflow:');
        $io->listing([
            'Create new index with updated mappings',
            'Reindex data from old index to new index',
            'Swap alias atomically (0ms downtime)',
            'Delete old index when ready',
        ]);

        return Command::SUCCESS;
    }
}
