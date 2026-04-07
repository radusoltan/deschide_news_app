<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\AggregatorRun;
use App\Message\Aggregator\ProcessAggregatorResultMessage;
use App\Service\Aggregator\AggregatorInterface;
use Doctrine\ORM\EntityManagerInterface;
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
        private readonly EntityManagerInterface $entityManager,
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

        $run = null;
        if (!$dryRun) {
            $run = new AggregatorRun();
            $run->setSource($sourceFilter);
            $run->setTriggeredBy('scheduler');
            $this->entityManager->persist($run);
            $this->entityManager->flush();
        }

        try {
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
                    $run?->addErrorDetail(sprintf('%s: %s', $aggregator->getSourceType()->value, $e->getMessage()));
                    $run?->incrementErrorsCount();
                    continue;
                }

                if ($limit > 0) {
                    $results = \array_slice($results, 0, $limit);
                }

                $totalResults += \count($results);
                $run?->incrementArticlesFound(\count($results));

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

            $run?->markCompleted();
            if ($run !== null) {
                $this->entityManager->flush();
            }

            $io->success(sprintf(
                'Aggregation complete. Total: %d results found, %d dispatched.',
                $totalResults,
                $dryRun ? 0 : $totalDispatched,
            ));

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            if ($run !== null) {
                $run->markFailed($e->getMessage());
                $this->entityManager->flush();
            }

            $io->error(sprintf('Aggregation failed: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }
}
