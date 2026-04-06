<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Aggregator\TrendQueryGeneratorService;
use App\Service\Aggregator\TrendScoringService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:trend:generate-queries',
    description: 'Generate aggregator queries from trending topics',
)]
final class GenerateTrendQueriesCommand extends Command
{
    public function __construct(
        private readonly TrendScoringService $scoringService,
        private readonly TrendQueryGeneratorService $queryGenerator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('top', null, InputOption::VALUE_REQUIRED, 'Number of top trending topics', '20')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Display results without caching');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $topN = (int) $input->getOption('top');
        $dryRun = $input->getOption('dry-run');

        $io->title('Trend Query Generator');

        // Show trending topics
        $trending = $this->scoringService->getTopTrendingTopics(days: 7, limit: $topN);

        if (empty($trending)) {
            $io->warning('No trending topics found in the last 7 days.');

            return Command::SUCCESS;
        }

        $io->section(sprintf('Top %d Trending Topics', \count($trending)));
        $io->table(
            ['Topic', 'Score', 'Articles (7d)', 'Velocity (art/day)'],
            array_map(fn (array $t) => [
                $t['topicName'],
                number_format($t['score'], 4),
                $t['articleCount'],
                number_format($t['velocity'], 2),
            ], $trending),
        );

        // Generate queries
        $queries = $this->queryGenerator->generateQueries($topN);

        if (empty($queries)) {
            $io->warning('No queries generated.');

            return Command::SUCCESS;
        }

        $io->section(sprintf('Generated %d Queries', \count($queries)));
        $io->table(
            ['Topic', 'Aggregator', 'Locale', 'Query', 'Score'],
            array_map(fn ($q) => [
                $q->topicName,
                $q->aggregatorType->value,
                $q->locale,
                mb_substr($q->formattedQuery, 0, 60),
                number_format($q->trendScore, 4),
            ], $queries),
        );

        if (!$dryRun) {
            $this->queryGenerator->cacheQueries($queries);
            $io->success(sprintf('Cached %d queries (TTL 6h).', \count($queries)));
        } else {
            $io->note('Dry run — queries not cached.');
        }

        return Command::SUCCESS;
    }
}
