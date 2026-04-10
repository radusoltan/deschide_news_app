<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\Editorial\ScrapeSourceMessage;
use App\MessageHandler\Editorial\ScrapeSourceHandler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:scrape:sources',
    description: 'Scrape surse editoriale (locale și internaționale)',
)]
final class ScrapeSourcesCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly ScrapeSourceHandler $scrapeHandler,
        #[Autowire(param: 'scraping.sources')]
        private readonly array $sources,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', 's', InputOption::VALUE_REQUIRED, 'Source key (moldpres, reuters_world, etc.)')
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Scrape all configured sources')
            ->addOption('international', 'i', InputOption::VALUE_NONE, 'Scrape only international sources')
            ->addOption('local', null, InputOption::VALUE_NONE, 'Scrape only local (Moldova) sources')
            ->addOption('language', 'l', InputOption::VALUE_REQUIRED, 'Specific language (ro, en, ru)')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max articles per source', '30')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be scraped without executing')
            ->addOption('async', null, InputOption::VALUE_NONE, 'Dispatch to queue instead of sync execution')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $sourceKey = $input->getOption('source');
        $all = $input->getOption('all');
        $international = $input->getOption('international');
        $local = $input->getOption('local');
        $language = $input->getOption('language');
        $limit = (int) $input->getOption('limit');
        $dryRun = $input->getOption('dry-run');
        $async = $input->getOption('async');

        if (!$all && !$international && !$local && $sourceKey === null) {
            $io->error('Specify --source=<key>, --all, --international, or --local');

            return Command::INVALID;
        }

        if ($international && $local) {
            $io->error('Cannot use --international and --local together. Use --all for both.');

            return Command::INVALID;
        }

        $sourcesToScrape = $this->resolveSourceKeys($sourceKey, $all, $international, $local);

        if ($sourcesToScrape === []) {
            $io->warning('No sources match the filter criteria.');

            return Command::SUCCESS;
        }

        if ($sourceKey !== null && !isset($this->sources[$sourceKey])) {
            $io->error(sprintf(
                'Unknown source "%s". Available: %s',
                $sourceKey,
                implode(', ', array_keys($this->sources)),
            ));

            return Command::INVALID;
        }

        try {
            if ($dryRun) {
                return $this->executeDryRun($io, $sourcesToScrape, $language, $limit);
            }

            if ($async) {
                return $this->executeAsync($io, $sourcesToScrape, $language, $limit);
            }

            return $this->executeSync($io, $output, $sourcesToScrape, $language, $limit);
        } catch (\Throwable $e) {
            $io->error('Scraping failed: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * @return list<string>
     */
    private function resolveSourceKeys(?string $sourceKey, bool $all, bool $international, bool $local): array
    {
        if ($sourceKey !== null) {
            return isset($this->sources[$sourceKey]) ? [$sourceKey] : [];
        }

        if ($all) {
            return array_keys($this->sources);
        }

        $keys = [];
        foreach ($this->sources as $key => $config) {
            $isInternational = (bool) ($config['is_international'] ?? false);
            if ($international && $isInternational) {
                $keys[] = $key;
            } elseif ($local && !$isInternational) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @param list<string> $sourcesToScrape
     */
    private function executeDryRun(SymfonyStyle $io, array $sourcesToScrape, ?string $language, int $limit): int
    {
        $io->title('DRY RUN — surse care ar fi scraped:');

        foreach ($sourcesToScrape as $key) {
            $source = $this->sources[$key];
            $feeds = $source['feed_urls'] ?? [];
            $isInt = (bool) ($source['is_international'] ?? false);
            $filter = (bool) ($source['relevance_filter'] ?? false);

            $io->section(sprintf(
                '%s (%s) — %s%s',
                $source['name'],
                $key,
                $isInt ? 'INTERNAȚIONAL' : 'LOCAL',
                $filter ? ' [filtru relevanță]' : '',
            ));

            foreach ($feeds as $lang => $url) {
                if ($language !== null && $language !== $lang) {
                    continue;
                }
                $io->writeln("  [{$lang}] {$url} (limit: {$limit})");
            }
        }

        $io->success('Dry run complete. No messages dispatched.');

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $sourcesToScrape
     */
    private function executeAsync(SymfonyStyle $io, array $sourcesToScrape, ?string $language, int $limit): int
    {
        $dispatched = 0;
        foreach ($sourcesToScrape as $key) {
            $io->writeln(sprintf('Dispatching scrape for <info>%s</info>...', $this->sources[$key]['name']));

            $this->messageBus->dispatch(new ScrapeSourceMessage(
                sourceKey: $key,
                language: $language,
                limit: $limit,
            ));

            $dispatched++;
        }

        $io->success("Dispatched {$dispatched} scraping message(s). Run messenger:consume scraping editorial to process.");

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $sourcesToScrape
     */
    private function executeSync(SymfonyStyle $io, OutputInterface $output, array $sourcesToScrape, ?string $language, int $limit): int
    {
        $io->title('Sentinel — Scraping în execuție');

        $allStats = [];
        foreach ($sourcesToScrape as $key) {
            $source = $this->sources[$key];
            $io->writeln(sprintf('Scraping <info>%s</info>...', $source['name']));

            $message = new ScrapeSourceMessage(
                sourceKey: $key,
                language: $language,
                limit: $limit,
            );

            $stats = ($this->scrapeHandler)($message);
            $allStats[$key] = $stats;
        }

        $this->renderStatsTable($output, $allStats);

        $totalAccepted = array_sum(array_column($allStats, 'accepted'));
        $io->success("Scraping complet. {$totalAccepted} articole acceptate.");

        return Command::SUCCESS;
    }

    /**
     * @param array<string, array{total: int, accepted: int, filtered: int, duplicates: int, errors: int}> $allStats
     */
    private function renderStatsTable(OutputInterface $output, array $allStats): void
    {
        $table = new Table($output);
        $table->setHeaders(['Sursă', 'Feed', 'Acceptat', 'Filtrat', 'Duplicate', 'Erori']);

        $intStats = ['total' => 0, 'accepted' => 0, 'filtered' => 0, 'duplicates' => 0, 'errors' => 0];
        $localStats = ['total' => 0, 'accepted' => 0, 'filtered' => 0, 'duplicates' => 0, 'errors' => 0];

        $intRows = [];
        $localRows = [];

        foreach ($allStats as $key => $stats) {
            $source = $this->sources[$key];
            $isInt = (bool) ($source['is_international'] ?? false);

            $row = [
                $source['name'],
                (string) $stats['total'],
                (string) $stats['accepted'],
                (string) $stats['filtered'],
                (string) $stats['duplicates'],
                (string) $stats['errors'],
            ];

            if ($isInt) {
                $intRows[] = $row;
                foreach (['total', 'accepted', 'filtered', 'duplicates', 'errors'] as $k) {
                    $intStats[$k] += $stats[$k];
                }
            } else {
                $localRows[] = $row;
                foreach (['total', 'accepted', 'filtered', 'duplicates', 'errors'] as $k) {
                    $localStats[$k] += $stats[$k];
                }
            }
        }

        // International sources
        foreach ($intRows as $row) {
            $table->addRow($row);
        }

        if ($intRows !== []) {
            $table->addRow(new TableSeparator());
            $table->addRow([
                '<info>TOTAL int.</info>',
                (string) $intStats['total'],
                (string) $intStats['accepted'],
                (string) $intStats['filtered'],
                (string) $intStats['duplicates'],
                (string) $intStats['errors'],
            ]);
        }

        // Local sources
        if ($localRows !== [] && $intRows !== []) {
            $table->addRow(new TableSeparator());
        }
        foreach ($localRows as $row) {
            $table->addRow($row);
        }

        if ($localRows !== []) {
            $table->addRow(new TableSeparator());
            $table->addRow([
                '<info>TOTAL local</info>',
                (string) $localStats['total'],
                (string) $localStats['accepted'],
                (string) $localStats['filtered'],
                (string) $localStats['duplicates'],
                (string) $localStats['errors'],
            ]);
        }

        // Grand total
        if ($intRows !== [] && $localRows !== []) {
            $table->addRow(new TableSeparator());
            $table->addRow([
                '<comment>GRAND TOTAL</comment>',
                (string) ($intStats['total'] + $localStats['total']),
                (string) ($intStats['accepted'] + $localStats['accepted']),
                (string) ($intStats['filtered'] + $localStats['filtered']),
                (string) ($intStats['duplicates'] + $localStats['duplicates']),
                (string) ($intStats['errors'] + $localStats['errors']),
            ]);
        }

        $table->render();
    }
}
