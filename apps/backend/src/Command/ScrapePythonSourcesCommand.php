<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\PressReleaseRepository;
use App\Service\ContentHasher;
use App\Service\Scraping\PressReleaseFromScraperFactory;
use App\Service\Scraping\PythonScraperException;
use App\Service\Scraping\PythonScraperService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:scrape:python-sources',
    description: 'Run Python scraper for all or selected YAML-configured sources',
)]
class ScrapePythonSourcesCommand extends Command
{
    private EntityManagerInterface $em;

    public function __construct(
        private readonly PythonScraperService $scraperService,
        private readonly PressReleaseFromScraperFactory $factory,
        private readonly PressReleaseRepository $pressReleaseRepository,
        private readonly ContentHasher $contentHasher,
        private readonly ManagerRegistry $doctrine,
    ) {
        /** @var EntityManagerInterface $em */
        $em = $doctrine->getManager();
        $this->em = $em;
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('source', null, InputOption::VALUE_REQUIRED, 'Single source key from YAML config')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Filter by extractor type (gov-rss, dom-scraper, pdf-extractor)')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max items per source', '20')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would happen without persisting')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $sourceFilter = $input->getOption('source');
        $typeFilter = $input->getOption('type');
        $limit = (int) $input->getOption('limit');
        $dryRun = $input->getOption('dry-run');

        $io->title('Python Scraper — Batch Runner');

        if ($dryRun) {
            $io->note('DRY RUN mode — no data will be persisted');
        }

        // Load sources from YAML
        $sources = $this->scraperService->getConfiguredSources($typeFilter);

        if ($sourceFilter) {
            $allSources = $this->scraperService->getConfiguredSources();
            if (!isset($allSources[$sourceFilter])) {
                $io->error(sprintf('Unknown source key "%s". Available: %s', $sourceFilter, implode(', ', array_keys($allSources))));

                return Command::FAILURE;
            }
            $sources = [$sourceFilter => $allSources[$sourceFilter]];
        }

        if (empty($sources)) {
            $io->warning('No enabled sources found matching your filters.');

            return Command::SUCCESS;
        }

        $io->text(sprintf('Processing %d source(s)...', \count($sources)));
        $io->newLine();

        $progressBar = new ProgressBar($output, \count($sources));
        $progressBar->setFormat(' %current%/%max% [%bar%] %percent:3s%% %message%');
        $progressBar->start();

        $totalScraped = 0;
        $totalCreated = 0;
        $totalSkippedUrl = 0;
        $totalSkippedHash = 0;
        $totalErrors = 0;
        $sourceResults = [];

        foreach ($sources as $key => $cfg) {
            $progressBar->setMessage($key);
            $progressBar->advance();

            $scraped = 0;
            $created = 0;
            $skippedUrl = 0;
            $skippedHash = 0;
            $error = null;

            try {
                $result = $this->scraperService->fetch($cfg['url'], $cfg['type'], $limit);
                $items = $result['items'];
                $scraped = \count($items);
                $batchHashes = [];

                foreach ($items as $item) {
                    // Dedup: sourceUrl
                    if (!empty($item['source_url']) && $this->pressReleaseRepository->findBySourceUrl($item['source_url']) !== null) {
                        ++$skippedUrl;
                        continue;
                    }

                    // Dedup: contentHash (DB + within-batch)
                    $hash = $this->contentHasher->hash($item['content']);
                    if (isset($batchHashes[$hash]) || $this->pressReleaseRepository->findByContentHash($hash) !== null) {
                        ++$skippedHash;
                        continue;
                    }
                    $batchHashes[$hash] = true;

                    if (!$dryRun) {
                        $pr = $this->factory->create($item);
                        $this->em->persist($pr);
                    }
                    ++$created;
                }

                if (!$dryRun && $created > 0) {
                    $this->em->flush();
                    $this->em->clear();
                }
            } catch (PythonScraperException $e) {
                $error = $e->getMessage();
                ++$totalErrors;
            } catch (\Throwable $e) {
                $error = $e->getMessage();
                ++$totalErrors;
                // Reset EntityManager so subsequent sources can persist
                if (!$this->em->isOpen()) {
                    $this->doctrine->resetManager();
                    /** @var EntityManagerInterface $em */
                    $em = $this->doctrine->getManager();
                    $this->em = $em;
                }
            }

            $sourceResults[] = [
                $key,
                $cfg['name'],
                $cfg['type'],
                $scraped,
                $created,
                $skippedUrl,
                $skippedHash,
                $error ? mb_substr($error, 0, 40) : 'OK',
            ];

            $totalScraped += $scraped;
            $totalCreated += $created;
            $totalSkippedUrl += $skippedUrl;
            $totalSkippedHash += $skippedHash;
        }

        $progressBar->setMessage('done');
        $progressBar->finish();
        $io->newLine(2);

        // Results table
        $io->section('Results per Source');
        $io->table(
            ['Key', 'Name', 'Type', 'Scraped', $dryRun ? 'Would Create' : 'Created', 'Skip(URL)', 'Skip(Hash)', 'Status'],
            $sourceResults,
        );

        // Summary
        $io->section('Summary');
        $io->table(
            ['Metric', 'Count'],
            [
                ['Sources processed', \count($sources)],
                ['Items scraped', $totalScraped],
                [$dryRun ? 'Would create' : 'Created', $totalCreated],
                ['Skipped (URL exists)', $totalSkippedUrl],
                ['Skipped (hash exists)', $totalSkippedHash],
                ['Source errors', $totalErrors],
            ],
        );

        if ($totalCreated > 0 || $totalScraped > 0) {
            $io->success(sprintf('%s %d PressRelease entries from %d scraped items.', $dryRun ? 'Would create' : 'Created', $totalCreated, $totalScraped));

            return Command::SUCCESS;
        }

        if ($totalErrors === \count($sources)) {
            $io->error('All sources failed.');

            return Command::FAILURE;
        }

        $io->note('No new items to create (all deduplicated).');

        return Command::SUCCESS;
    }
}
