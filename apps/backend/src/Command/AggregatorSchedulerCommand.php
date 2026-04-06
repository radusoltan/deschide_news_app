<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\Aggregator\ProcessAggregatorResultMessage;
use App\Service\Aggregator\AggregatorInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:aggregator:run',
    description: 'Run aggregator sources to discover diaspora news articles',
)]
class AggregatorSchedulerCommand extends Command
{
    /**
     * @param iterable<AggregatorInterface> $aggregators
     */
    public function __construct(
        #[AutowireIterator('app.aggregator')]
        private readonly iterable $aggregators,
        private readonly MessageBusInterface $messageBus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', 's', InputOption::VALUE_REQUIRED, 'Source to run (e.g. google_news_rss, google_alerts, all)', 'all')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'List results without dispatching to messenger')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, 'Max results per source', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sourceFilter = $input->getOption('source');
        $dryRun = $input->getOption('dry-run');
        $limit = (int) $input->getOption('limit');

        $io->title('Aggregator Scheduler');

        if ($dryRun) {
            $io->note('Dry run mode — results will NOT be dispatched.');
        }

        $totalResults = 0;
        $totalDispatched = 0;

        foreach ($this->aggregators as $aggregator) {
            if ($sourceFilter !== 'all' && $aggregator->getSourceType()->value !== $sourceFilter) {
                continue;
            }

            $io->section(sprintf('Source: %s (%s)', $aggregator->getName(), $aggregator->getSourceType()->value));

            try {
                $results = $aggregator->fetch();
            } catch (\Throwable $e) {
                $io->error(sprintf('Failed to fetch: %s', $e->getMessage()));
                continue;
            }

            if ($limit > 0) {
                $results = \array_slice($results, 0, $limit);
            }

            $totalResults += \count($results);

            if ($dryRun) {
                $rows = [];
                foreach ($results as $result) {
                    $rows[] = [
                        mb_substr($result->title, 0, 60),
                        $result->sourceLanguage,
                        $result->sourceName,
                        $result->publishedAt->format('Y-m-d H:i'),
                    ];
                }

                $io->table(['Title', 'Language', 'Source', 'Published'], $rows);
                $io->info(sprintf('Found %d results.', \count($results)));

                continue;
            }

            // Dispatch to Messenger
            foreach ($results as $result) {
                $message = new ProcessAggregatorResultMessage(
                    title: $result->title,
                    summary: $result->summary,
                    sourceUrl: $result->sourceUrl,
                    sourceLanguage: $result->sourceLanguage,
                    sourceName: $result->sourceName,
                    publishedAt: $result->publishedAt->format('c'),
                    rawContent: $result->rawContent,
                    keywords: $result->keywords,
                    aggregatorSourceType: $result->aggregatorSourceType?->value ?? '',
                );

                $this->messageBus->dispatch($message);
                ++$totalDispatched;
            }

            $io->info(sprintf('Dispatched %d results.', \count($results)));
        }

        $io->success(sprintf(
            'Aggregation complete. Total: %d results found, %d dispatched.',
            $totalResults,
            $dryRun ? 0 : $totalDispatched,
        ));

        return Command::SUCCESS;
    }
}
